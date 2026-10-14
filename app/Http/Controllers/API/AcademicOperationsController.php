<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\UserCareer;
use App\Services\AcademicWorkflowService;
use App\Services\AcademicMembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicOperationsController extends Controller
{
    public function __construct(private AcademicWorkflowService $workflow) {}

    public function catalog()
    {
        $this->workflow->ready();
        $career = $this->workflow->careerId();
        $user = auth('api')->user();
        return response()->json([
            'courses' => $user->isStudent() ? [] : \App\Models\Course::with(['subject', 'group'])->where('activo', true)->get(),
            'evaluations' => $user->isStudent() ? [] : \App\Models\Evaluation::with('project')->when(!$user->canManageProjects(), fn ($q) => $q->whereHas('room.teachers', fn ($t) => $t->where('usuarios.id', $user->id)))->get(),
            'advisors' => $user->isStudent() ? \App\Models\User::whereIn('id', DB::table('proyecto_integrantes')->whereIn('proyecto_id', \App\Models\Project::whereHas('students', fn ($q) => $q->where('usuarios.id', $user->id))->select('id'))->whereNotIn('rol', ['lider', 'integrante'])->select('usuario_id'))->get(['id', 'nombres', 'apellido_paterno']) : [],
            'periods' => \App\Models\AcademicPeriod::orderByDesc('fecha_inicio')->get(),
            'groups' => SubjectGroup::where('activo', true)->get(),
            'rooms' => DB::table('salones_institucionales')->where('activo', true)->orderBy('nombre')->get(),
            'projects' => \App\Models\Project::where('activo', true)->when($user->isStudent(), fn ($q) => $q->whereHas('students', fn ($s) => $s->where('usuarios.id', $user->id)))->get(['id', 'titulo', 'empresa_id']),
            'members' => $user->isStudent() ? [] : DB::table('usuario_carrera as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
                ->where('m.carrera_id', $career)->where('m.activo', true)->where('u.activo', true)
                ->select('m.id', 'm.usuario_id', 'm.perfil_id', 'u.nombres', 'u.apellido_paterno')->orderBy('u.nombres')->get(),
        ]);
    }

    public function enrollments(Request $request)
    {
        abort_unless(auth('api')->user()->canManageAcademics(), 403);
        $period = $request->validate(['period_id' => 'required|integer|exists:periodos_academicos,id']);
        return response()->json(['data' => DB::table('matriculas_academicas')->where('carrera_id', $this->workflow->careerId())->where('periodo_id', $period['period_id'])->get()]);
    }

    public function assign(Request $request)
    {
        abort_unless(auth('api')->user()->canManageAcademics(), 403);
        $data = $request->validate(['student_id' => 'required|string', 'group_id' => 'required|integer']);
        $this->workflow->assign($data['student_id'], SubjectGroup::findOrFail($data['group_id']));
        return response()->json(['message' => 'Matrícula actualizada']);
    }

    public function membership(Request $request, int $membership)
    {
        $this->workflow->manager();
        $data = $request->validate(['profile_id' => 'required|integer|in:2,3,6,7']);
        return DB::transaction(function () use ($membership, $data) {
            $row = UserCareer::where('carrera_id', $this->workflow->careerId())->where('activo', true)->lockForUpdate()->findOrFail($membership);
            abort_unless(in_array((int) $row->perfil_id, [2, 3, 6, 7], true), 403, 'Sólo administración general modifica cargos de jefatura o administración.');
            abort_if((string) $row->usuario_id === (string) auth('api')->id(), 403, 'No puedes cambiar tu propio perfil.');
            if ((int) $row->perfil_id === (int) $data['profile_id']) return response()->json(['message' => 'El perfil ya está asignado']);
            app(AcademicMembershipService::class)->ensureReleased($row);
            if ((int) $data['profile_id'] === 3) abort_if(UserCareer::where('usuario_id', $row->usuario_id)->where('activo', true)->where('perfil_id', 3)->where('carrera_id', '!=', $row->carrera_id)->exists(), 422, 'El alumno ya pertenece a otra carrera.');
            $before = $row->toArray();
            $row->update(['perfil_id' => $data['profile_id'], 'asignado_por' => auth('api')->id()]);
            $this->workflow->audit('membresia.perfil', 'membresia', $row->id, $before, $row->toArray());
            return response()->json(['message' => 'Perfil operativo actualizado']);
        });
    }

    public function commitments(int $membership)
    {
        $this->workflow->manager();
        $row = UserCareer::when(!auth('api')->user()->isGeneralAdmin(), fn ($q) => $q->where('carrera_id', $this->workflow->careerId()))->findOrFail($membership);
        return response()->json(app(AcademicMembershipService::class)->commitments($row->usuario_id, $row->carrera_id));
    }

    public function classroom(Request $request)
    {
        abort_unless(auth('api')->user()->isGeneralAdmin(), 403);
        $data = $request->validate(['name' => 'required|string|max:120']);
        $name = preg_replace('/\s+/u', ' ', trim($data['name']));
        $key = mb_strtolower($name);
        DB::table('salones_institucionales')->insertOrIgnore(['nombre' => $name, 'clave' => $key, 'activo' => true]);
        return response()->json(['message' => 'Salón registrado']);
    }

    public function projectPeriod(Request $request)
    {
        abort_unless(auth('api')->user()->canManageProjects(), 403);
        $data = $request->validate(['project_id' => 'required|integer', 'group_id' => 'required|integer']);
        $project = \App\Models\Project::findOrFail($data['project_id']);
        $group = SubjectGroup::where('activo', true)->findOrFail($data['group_id']);
        $this->workflow->requireOpen((int) $group->periodo_id);
        DB::transaction(function () use ($project, $group) {
            $key = ['proyecto_id' => $project->id, 'periodo_id' => $group->periodo_id];
            $before = DB::table('proyecto_periodos')->where($key)->first();
            abort_if($before && (int) $before->grupo_id !== (int) $group->id, 409, 'La participación ya fue registrada; no se modifica el historial.');
            DB::table('proyecto_periodos')->updateOrInsert($key, ['carrera_id' => $this->workflow->careerId(), 'grupo_id' => $group->id, 'created_at' => $before->created_at ?? now(), 'updated_at' => now()]);
            $this->workflow->audit('proyecto.continuidad', 'proyecto', $project->id, $before, $key + ['grupo_id' => $group->id]);
        });
        return response()->json(['message' => 'Continuidad del proyecto registrada']);
    }
    public function reconciliation(Request $request, \App\Models\AcademicPeriod $period)
    {
        $this->workflow->manager();
        $service = app(\App\Services\AcademicReconciliationService::class);
        if ($request->isMethod('GET')) return response()->json($service->report($period));
        $data = $request->validate(['snapshot' => 'required|string|size:64', 'resolutions' => 'present|array',
            'resolutions.*.student_id' => 'required|string|distinct', 'resolutions.*.group_id' => 'nullable|integer',
            'resolutions.*.reason' => 'required|string|max:500', 'room_resolutions' => 'sometimes|array', 'room_resolutions.*.room_id' => 'required|integer|distinct',
            'room_resolutions.*.period_id' => 'required|integer', 'room_resolutions.*.classroom_id' => 'required|integer',
            'room_resolutions.*.reason' => 'required|string|max:500', 'acknowledge_legacy_exceptions' => 'required|boolean']);
        $service->apply($period, $data);
        return response()->json(['message' => 'Conciliación aplicada; revisa excepciones históricas antes de abrir']);
    }

    public function authorizations()
    {
        $user = auth('api')->user();
        abort_if($user->isStudent(), 403);
        return response()->json(['data' => DB::table('autorizaciones_academicas')->where('carrera_id', $this->workflow->careerId())
            ->when(!$user->canManageProjects(), fn ($q) => $q->where('solicitada_por', $user->id))->orderByDesc('id')->get()]);
    }

}
