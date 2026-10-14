<?php
namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcademicAuthorizationService
{
    public function __construct(private AcademicWorkflowService $workflow) {}

    public function valid(string $type, int $project, int $period)
    {
        return DB::table('autorizaciones_academicas')->where('carrera_id', $this->workflow->careerId())
            ->where('periodo_id', $period)->where('proyecto_id', $project)->where('tipo', $type)
            ->where('estado', 'aprobada')->where('vigente_hasta', '>=', now())->whereNull('consumida_en')->orderByDesc('id');
    }

    public function request(array $data): object
    {
        $this->workflow->requireOpen((int) $data['period_id']);
        $user = auth('api')->user();
        abort_unless($user && !$user->isStudent(), 403);
        $project = !empty($data['project_id']) ? Project::where('activo', true)->findOrFail($data['project_id']) : null;
        if (!empty($data['student_id'])) User::students()->where('activo', true)->findOrFail($data['student_id']);
        if ($data['type'] === 'inscripcion_semestre') {
            $this->workflow->manager();
            $course = \App\Models\Course::findOrFail($data['course_id'] ?? null);
            abort_unless($course->group?->periodo_id == $data['period_id'] && !empty($data['student_id']), 422, 'Selecciona alumno y curso del periodo.');
        } else {
            abort_unless($project, 422, 'Selecciona explícitamente el proyecto de la autorización.');
            if (!empty($data['student_id'])) abort_unless($project->students()->where('usuarios.id', $data['student_id'])->exists(), 422, 'El alumno no pertenece al proyecto.');
            if (!$user->canManageProjects()) abort_unless($project->advisors()->where('usuarios.id', $user->id)->exists(), 403, 'No tienes asignado este proyecto.');
            if (in_array($data['type'], ['reintento', 'reprogramacion'])) {
                abort_unless(DB::table('evaluaciones')->where('id', $data['evaluation_id'] ?? null)->where('proyecto_id', $project->id)
                    ->where('carrera_id', $this->workflow->careerId())->exists(), 422, 'Selecciona la evaluación de origen.');
            }
        }
        return DB::transaction(function () use ($data, $project, $user) {
            $id = DB::table('autorizaciones_academicas')->insertGetId([
                'carrera_id' => $this->workflow->careerId(), 'periodo_id' => $data['period_id'],
                'proyecto_id' => $project?->id, 'estudiante_id' => $data['student_id'] ?? null,
                'curso_id' => $data['course_id'] ?? null, 'evaluacion_id' => $data['evaluation_id'] ?? null,
                'tipo' => $data['type'], 'semestre_destino' => $data['presentation_semester'] ?? null,
                'estado' => 'solicitada', 'motivo' => $data['reason'], 'vigente_hasta' => $data['expires_at'],
                'solicitada_por' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('autorizaciones_academicas')->find($id);
            $this->workflow->audit('autorizacion.solicitada', 'autorizacion', $id, null, $row);
            return $row;
        });
    }

    public function decide(int $id, array $data): object
    {
        $this->workflow->manager();
        return DB::transaction(function () use ($id, $data) {
            $query = DB::table('autorizaciones_academicas')->where('carrera_id', $this->workflow->careerId())->where('id', $id);
            $old = (clone $query)->lockForUpdate()->first();
            abort_unless($old, 404);
            abort_unless((int) $old->version === (int) $data['version'], 409, 'La solicitud cambió. Actualiza la vista.');
            $allowed = $old->estado === 'solicitada' ? ['aprobada', 'rechazada'] : ($old->estado === 'aprobada' ? ['revocada'] : []);
            abort_unless(in_array($data['status'], $allowed, true) && !$old->consumida_en, 409, 'Esta autorización ya fue resuelta o utilizada.');
            if ($data['status'] === 'aprobada') {
                $this->workflow->requireOpen((int) $old->periodo_id);
                abort_unless(now()->lte($old->vigente_hasta), 422, 'La solicitud está vencida.');
                abort_unless(!$old->proyecto_id || ($data['project_scope'] ?? false), 422, 'Confirma el alcance sobre todo el proyecto.');
            }
            $query->update(['estado' => $data['status'], 'resuelta_por' => auth('api')->id(), 'resolucion' => $data['reason'],
                'version' => $old->version + 1, 'updated_at' => now()]);
            $new = $query->first();
            $this->workflow->audit('autorizacion.'.$data['status'], 'autorizacion', $id, $old, $new);
            return $new;
        });
    }
}
