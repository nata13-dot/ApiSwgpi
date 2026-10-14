<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CourseEnrollmentController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        $coursesQuery = Course::with(['subject:id,clave,nombre', 'group:id,nombre,semestre,grupo,periodo_id'])
            ->where('activo', true)
            ->whereHas('group', fn ($query) => $query->where('activo', true));
        $period = app(\App\Services\AcademicWorkflowService::class)->period();
        $coursesQuery->whereHas('group', fn ($query) => $query->where('periodo_id', $period?->id ?? 0));
        if ($user->isStudent()) $coursesQuery->where('es_seguimiento_proyecto', true);
        if (!$user->isStudent() && !$user->canManageAcademics()) {
            $coursesQuery->whereExists(fn ($query) => $query->selectRaw('1')->from('curso_docentes')
                ->whereColumn('curso_docentes.curso_id', 'cursos.id')->where('curso_docentes.docente_id', $user->id)->where('curso_docentes.activo', true));
        }
        $courses = $coursesQuery->orderBy('grupo_id')->get();

        $enrolled = DB::table('curso_estudiantes')->where('estudiante_id', $user->id)
            ->where('activo', true)->pluck('curso_id')->map(fn ($id) => (int) $id)->all();

        if ($user->isStudent()) $courses = $courses->filter(function ($course) use ($user, $enrolled) {
            if (in_array((int) $course->id, $enrolled, true)) return true;
            try { app(\App\Services\CourseAdmissionService::class)->check($course, $user); return true; }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { if (!in_array($e->getStatusCode(), [409, 422], true)) throw $e; return false; }
        })->values();
        return response()->json(['data' => $courses->map(function (Course $course) use ($enrolled) {
            $course->setAttribute('inscrito', in_array((int) $course->id, $enrolled, true));
            $course->setAttribute('clave_configurada', !empty($course->getRawOriginal('clave_autoregistro')));
            return $course;
        })]);
    }

    public function enroll(Request $request, Course $course)
    {
        $validated = $request->validate(['password' => 'required|string|max:100']);
        $user = auth('api')->user();
        if (!$course->activo || !$course->es_seguimiento_proyecto) {
            return response()->json(['message' => 'La materia no admite autoregistro.'], 422);
        }
        app(\App\Services\CourseAdmissionService::class)->check($course, $user);
        $hash = $course->getRawOriginal('clave_autoregistro');
        if (!$hash || !Hash::check($validated['password'], $hash)) {
            throw ValidationException::withMessages(['password' => ['La contraseña de autoregistro no es correcta.']]);
        }
        DB::transaction(function () use ($course, $user, $validated) {
            $lockedCourse = Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedCourse->getRawOriginal('clave_autoregistro') && Hash::check($validated['password'], $lockedCourse->getRawOriginal('clave_autoregistro')), 422, 'La clave cambió; solicita la clave vigente.');
            app(\App\Services\CourseAdmissionService::class)->check($course->fresh(), $user);
            if (app(\App\Services\CourseAdmissionService::class)->enrolled($course->id, $user->id)) return;
            DB::table('curso_estudiantes')->updateOrInsert(['curso_id' => $course->id, 'estudiante_id' => $user->id],
                ['inscrito_en' => now(), 'activo' => true]);
            app(\App\Services\AcademicWorkflowService::class)->audit('curso.inscripcion', 'curso', $course->id, null, ['student_id' => $user->id]);
        });
        return response()->json(['message' => 'Te inscribiste correctamente a la materia de seguimiento.']);
    }

    public function updateAccess(Request $request, Course $course)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'starts_at' => 'nullable|required_if:enabled,true|date',
            'ends_at' => 'nullable|required_if:enabled,true|date|after:starts_at',
            'password' => 'nullable|required_if:enabled,true|string|min:6|max:100|confirmed',
        ]);
        $user = auth('api')->user();
        abort_unless((int) $course->carrera_id === app(\App\Services\AcademicWorkflowService::class)->careerId(), 404);
        $assigned = DB::table('curso_docentes')->where('curso_id', $course->id)
            ->where('docente_id', $user->id)->where('activo', true)->exists();
        if (!$assigned && !$user->canManageAcademics()) abort(403, 'No impartes este curso.');

        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $course->group->periodo_id);
        $course->inscripcion_desde = $validated['starts_at'] ?? null;
        $course->inscripcion_hasta = $validated['ends_at'] ?? null;
        $course->es_seguimiento_proyecto = $validated['enabled'];
        if (!empty($validated['password'])) $course->clave_autoregistro = Hash::make($validated['password']);
        if (!$validated['enabled']) $course->clave_autoregistro = null;
        $course->clave_actualizada_en = now();
        $course->clave_actualizada_por = $user->id;
        $course->save();
        return response()->json(['message' => 'Acceso de autoregistro actualizado.']);
    }
    public function students(Course $course)
    {
        app(\App\Services\CourseAdmissionService::class)->manage($course, auth('api')->user());
        return response()->json(['data' => $course->students()->get(['usuarios.id', 'nombres', 'apellido_paterno', 'apellido_materno'])]);
    }

    public function leave(Request $request, Course $course)
    {
        $user = auth('api')->user();
        $data = $request->validate(['student_id' => 'nullable|string', 'reason' => 'required|string|max:500']);
        app(\App\Services\AcademicWorkflowService::class)->requireOpen((int) $course->group->periodo_id);
        $student = $user->isStudent() ? $user->id : ($data['student_id'] ?? '');
        if (!$user->isStudent()) abort_unless($user->canManageAcademics(), 403);
        DB::transaction(function () use ($course, $user, $student, $data) {
            Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
            if ($user->isStudent()) {
                app(\App\Services\CourseAdmissionService::class)->check($course->fresh(), $user);
                $participated = DB::table('entrega_participantes as p')->join('entregas as e', 'e.id', '=', 'p.entrega_id')
                    ->join('entregables as d', 'd.id', '=', 'e.entregable_id')->where('d.curso_id', $course->id)->where('p.estudiante_id', $student)->exists();
                $participated = $participated || DB::table('entregas as e')
                    ->join('entregables as d', 'd.id', '=', 'e.entregable_id')
                    ->where('d.curso_id', $course->id)->where('e.enviado_por', $student)->exists();
                abort_if($participated, 409, 'La baja requiere gestión académica porque ya tienes entregas.');
            }
            DB::table('curso_estudiantes')->where('curso_id', $course->id)->where('estudiante_id', $student)->update(['activo' => false]);
            app(\App\Services\AcademicWorkflowService::class)->audit('curso.baja', 'curso', $course->id, null, $data + ['student_id' => $student]);
        });
        return response()->json(['message' => 'Baja registrada; se conserva el historial']);
    }

}
