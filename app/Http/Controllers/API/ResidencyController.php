<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\AcademicWorkflowService;
use App\Services\StoredFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResidencyController extends Controller
{
    public function __construct(private AcademicWorkflowService $workflow, private StoredFileService $files) {}

    private function query()
    {
        $user = auth('api')->user();
        return DB::table('residencias')->where('carrera_id', $this->workflow->careerId())
            ->when($user->isStudent(), fn ($q) => $q->where('estudiante_id', $user->id))
            ->when(!$user->isStudent() && !$user->canManageProjects(), fn ($q) => $q->where('asesor_id', $user->id));
    }

    private function get(int $id, bool $lock = false): object
    {
        $row = $this->query()->when($lock, fn ($q) => $q->lockForUpdate())->find($id);
        abort_unless($row, 404);
        return $row;
    }

    private function editable(object $row, bool $review = false): void
    {
        $review ? $this->workflow->requireReviewable((int) $row->periodo_id) : $this->workflow->requireOpen((int) $row->periodo_id);
        abort_unless($row->estado === 'abierta', 409, 'El expediente está cerrado.');
    }

    public function index()
    {
        return response()->json(['data' => $this->query()->orderByDesc('id')->get()->map(function ($row) {
            $row->requirements = DB::table('residencia_requisitos')->where('residencia_id', $row->id)->get()->map(function ($requirement) {
                $requirement->versions = DB::table('residencia_versiones')->where('requisito_id', $requirement->id)
                    ->orderByDesc('numero')->get(['id', 'numero', 'nombre', 'estado', 'observaciones', 'revisada_por', 'created_at']);
                return $requirement;
            });
            return $row;
        })]);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();
        abort_unless($user->isStudent() || $user->canManageAcademics(), 403);
        $data = $request->validate([
            'student_id' => 'required|string', 'period_id' => 'required|integer', 'project_id' => 'required|integer',
            'company_id' => 'required|integer|exists:empresas,id', 'advisor_id' => 'required|string',
            'origin' => 'required|in:empresa,continuacion', 'changes' => 'required|string|min:20|max:5000',
        ]);
        abort_if($user->isStudent() && $data['student_id'] !== $user->id, 403);
        $this->workflow->requireOpen($data['period_id']);
        $enrollment = $this->workflow->enrollment($data['student_id'], $data['period_id']);
        abort_unless($enrollment && (int) $enrollment->semestre === 9, 422, 'El expediente requiere matrícula en noveno.');
        $project = Project::where('activo', true)->findOrFail($data['project_id']);
        abort_unless($project->students()->where('usuarios.id', $data['student_id'])->exists(), 422, 'El alumno no integra el proyecto.');
        $this->workflow->teacher($data['advisor_id']);
        abort_unless($project->advisors()->where('usuarios.id', $data['advisor_id'])->exists(), 422, 'El asesor debe estar asignado al proyecto.');
        if ($data['origin'] === 'empresa') abort_unless($this->workflow->projectSemester($project, $data['period_id']) === 9, 422, 'El proyecto nuevo de empresa debe estar registrado para noveno en este periodo.');
        if ($data['origin'] === 'continuacion') abort_unless($project->modalidad === 'proyecto_integrador', 422, 'La continuación debe vincular un integrador.');
        abort_unless((int) $project->empresa_id === (int) $data['company_id'], 422, 'La empresa debe corresponder al proyecto.');
        $id = DB::transaction(function () use ($data) {
            DB::table('usuarios')->where('id', $data['student_id'])->lockForUpdate()->first();
            abort_if(DB::table('residencias')->where('estudiante_id', $data['student_id'])->where('periodo_id', $data['period_id'])->exists(), 409, 'Ya existe un expediente individual en este periodo.');
            $id = DB::table('residencias')->insertGetId([
                'carrera_id' => $this->workflow->careerId(), 'periodo_id' => $data['period_id'], 'estudiante_id' => $data['student_id'],
                'proyecto_id' => $data['project_id'], 'empresa_id' => $data['company_id'], 'asesor_id' => $data['advisor_id'],
                'origen' => $data['origin'], 'cambios_propuestos' => $data['changes'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['Plan de trabajo', 'Avance documental', 'Informe final'] as $name) DB::table('residencia_requisitos')->insert([
                'residencia_id' => $id, 'nombre' => $name, 'obligatorio' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->workflow->audit('residencia.creada', 'residencia', $id, null, $data);
            return $id;
        });
        return response()->json(['message' => 'Expediente creado', 'id' => $id], 201);
    }

    public function advisor(Request $request, int $residency)
    {
        $this->workflow->manager();
        $data = $request->validate(['advisor_id' => 'required|string', 'version' => 'required|integer', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($residency, $data) {
            $row = $this->get($residency, true); $this->editable($row);
            abort_unless((int) $row->version === (int) $data['version'], 409, 'Actualiza el expediente antes de reasignar.');
            $this->workflow->teacher($data['advisor_id']);
            abort_unless(Project::findOrFail($row->proyecto_id)->advisors()->where('usuarios.id', $data['advisor_id'])->exists(), 422, 'Asigna primero al asesor al proyecto.');
            DB::table('residencias')->where('id', $residency)->update(['asesor_id' => $data['advisor_id'], 'version' => $row->version + 1, 'updated_at' => now()]);
            $this->workflow->audit('residencia.asesor', 'residencia', $residency, $row, $data);
        });
        return response()->json(['message' => 'Asesor reasignado; se conservan las revisiones anteriores']);
    }

    public function requirement(Request $request, int $residency)
    {
        $row = $this->get($residency);
        $this->editable($row);
        abort_unless(auth('api')->id() === $row->asesor_id || auth('api')->user()->canManageAcademics(), 403);
        $data = $request->validate(['name' => 'required|string|max:180', 'required' => 'required|boolean', 'deadline' => 'nullable|date']);
        DB::transaction(function () use ($residency, $data) {
            $this->editable($this->get($residency, true));
            DB::table('residencia_requisitos')->insert(['residencia_id' => $residency, 'nombre' => $data['name'], 'obligatorio' => $data['required'], 'fecha_limite' => $data['deadline'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            $this->workflow->audit('residencia.requisito', 'residencia', $residency, null, $data);
        });
        return response()->json(['message' => 'Requisito agregado']);
    }

    public function upload(Request $request, int $residency, int $requirement)
    {
        $row = $this->get($residency);
        $this->editable($row);
        abort_unless(auth('api')->id() === $row->estudiante_id, 403, 'Sólo el residente puede subir su documentación.');
        $request->validate(['file' => 'required|file']);
        $prepared = $stored = null;
        try {
            $prepared = $this->files->prepare($request->file('file'), ['pdf', 'doc', 'docx'], 50);
            $stored = $this->files->promote($prepared, "residencias/{$residency}/{$requirement}", config('uploads.private_disk', 'swgpi_private'));
            DB::transaction(function () use ($residency, $requirement, $prepared, $stored) {
                $this->editable($this->get($residency, true));
                $item = DB::table('residencia_requisitos')->where('residencia_id', $residency)->where('id', $requirement)->lockForUpdate()->first();
                abort_unless($item, 404);
                abort_if($item->fecha_limite && now()->gt($item->fecha_limite), 422, 'El plazo de entrega terminó.');
                $number = (int) DB::table('residencia_versiones')->where('requisito_id', $requirement)->max('numero') + 1;
                DB::table('residencia_versiones')->insert([
                    'requisito_id' => $requirement, 'numero' => $number, 'disco' => $stored['disk'], 'ruta' => $stored['path'],
                    'nombre' => $prepared['original_name'], 'mime' => $prepared['mime_type'], 'bytes' => $prepared['size_bytes'],
                    'sha256' => $prepared['checksum_sha256'], 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('residencias')->where('id', $residency)->increment('version');
                $this->workflow->audit('residencia.entrega', 'residencia', $residency, null, ['requirement' => $requirement, 'version' => $number]);
            });
        } catch (\Throwable $e) {
            $this->files->discardPrepared($prepared); $this->files->discardStored($stored); throw $e;
        }
        return response()->json(['message' => 'Nueva versión registrada'], 201);
    }

    public function review(Request $request, int $residency, int $version)
    {
        $data = $request->validate(['status' => 'required|in:correcciones,validada', 'observations' => 'required|string|max:5000']);
        DB::transaction(function () use ($residency, $version, $data) {
            $row = $this->get($residency, true); $this->editable($row, true);
            abort_unless(auth('api')->id() === $row->asesor_id, 403, 'La revisión corresponde al asesor asignado.');
            $item = DB::table('residencia_versiones')->whereIn('requisito_id', DB::table('residencia_requisitos')->where('residencia_id', $residency)->select('id'))->lockForUpdate()->find($version);
            abort_unless($item, 404);
            abort_unless($item->estado === 'pendiente', 409, 'La revisión de esta versión ya fue registrada.');
            DB::table('residencia_versiones')->where('id', $version)->update(['estado' => $data['status'], 'observaciones' => $data['observations'], 'revisada_por' => auth('api')->id(), 'updated_at' => now()]);
            DB::table('residencias')->where('id', $residency)->increment('version');
            $this->workflow->audit('residencia.revision', 'residencia_version', $version, $item, $data);
        });
        return response()->json(['message' => 'Revisión registrada']);
    }

    public function close(Request $request, int $residency)
    {
        $this->workflow->manager();
        $data = $request->validate(['version' => 'required|integer', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($residency, $data) {
            $row = $this->get($residency, true); $this->editable($row, true);
            abort_unless((int) $row->version === (int) $data['version'], 409, 'El expediente cambió. Actualiza la vista.');
            $requirements = DB::table('residencia_requisitos')->where('residencia_id', $residency)->where('obligatorio', true)->get();
            abort_if($requirements->isEmpty(), 422, 'Se requiere documentación obligatoria.');
            foreach ($requirements as $item) {
                $latest = DB::table('residencia_versiones')->where('requisito_id', $item->id)->orderByDesc('numero')->first();
                abort_unless($latest && $latest->estado === 'validada', 409, 'Falta validar la última versión de documentos obligatorios.');
            }
            DB::table('residencias')->where('id', $residency)->update(['estado' => 'cerrada', 'version' => $row->version + 1, 'updated_at' => now()]);
            $this->workflow->audit('residencia.cierre', 'residencia', $residency, $row, $data);
        });
        return response()->json(['message' => 'Seguimiento cerrado; no representa egreso oficial']);
    }

    public function download(int $residency, int $version)
    {
        $this->get($residency);
        $item = DB::table('residencia_versiones')->whereIn('requisito_id', DB::table('residencia_requisitos')->where('residencia_id', $residency)->select('id'))->find($version);
        abort_unless($item, 404);
        return Storage::disk($item->disco)->download($item->ruta, $item->nombre, ['Content-Type' => $item->mime, 'Cache-Control' => 'no-store']);
    }
}
