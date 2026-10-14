<?php
namespace App\Services;

use App\Models\EvaluationRoom;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EvaluationAgendaService
{
    public function __construct(private AcademicWorkflowService $workflow, private AcademicAuthorizationService $authorizations) {}

    /** Must be called inside the same transaction that persists the room and teachers. */
    public function validate(array $data, ?EvaluationRoom $room = null): array
    {
        $period = (int) ($data['periodo_id'] ?? $room?->periodo_id ?? $this->workflow->period()?->id);
        $this->workflow->requireOpen($period);
        $teachers = collect($data['teacher_ids'])->map(fn ($id) => (string) $id)->unique()->sort()->values();
        foreach ($teachers as $teacher) $this->workflow->teacher($teacher);
        $salon = DB::table('salones_institucionales')->where('activo', true)->find($data['salon_id'] ?? $room?->salon_id);
        abort_unless($salon, 422, 'Selecciona un salón del catálogo institucional.');
        $start = Carbon::parse($data['fecha_evaluacion']);
        $end = Carbon::parse(!empty($data['allow_late_evaluations']) ? $data['late_evaluation_until'] : $data['fecha_fin_evaluacion']);
        abort_unless($end->gt($start), 422, 'Horario inválido.');
        $projects = collect($data['project_ids'])->map(fn ($id) => (int) $id)->unique();
        $required = $projects->count() * ((int) $data['project_presentation_minutes'] + (int) $data['teacher_evaluation_minutes']);
        abort_if($start->diffInMinutes(Carbon::parse($data['fecha_fin_evaluacion'])) < $required, 422, 'El horario no alcanza para todas las presentaciones y evaluaciones.');
        $keys = $teachers->map(fn ($id) => 'docente:'.$id)->push('salon:'.$salon->id)
            ->merge($projects->map(fn ($id) => 'proyecto:'.$id))->sort()->values();
        foreach ($keys as $key) {
            DB::table('recursos_agenda')->insertOrIgnore(['id' => $key]);
            DB::table('recursos_agenda')->where('id', $key)->lockForUpdate()->first();
        }
        $overlap = DB::table('salas_evaluacion as s')->where('s.id', '!=', $room?->id ?? 0)
            ->where('s.estado', '!=', 'finalizada')->where('s.inicia_en', '<', $end)
            ->whereRaw('COALESCE(s.evaluacion_fuera_horario_hasta, s.termina_en) > ?', [$start]);
        // Joining the reservation rows makes both resources current reads under InnoDB.
        // A correlated EXISTS can otherwise retain the earlier repeatable-read snapshot.
        $classroomConflict = (clone $overlap)->where(fn ($q) => $q->where('s.salon_id', $salon->id)
            ->orWhereRaw('LOWER(TRIM(s.salon)) = ?', [mb_strtolower(trim($salon->nombre))]))->lockForUpdate()->first();
        $teacherConflict = (clone $overlap)->join('sala_docentes as sd', 'sd.sala_id', '=', 's.id')
            ->whereIn('sd.docente_id', $teachers)->lockForUpdate()->first();
        abort_if($classroomConflict || $teacherConflict, 409, 'El salón o un docente ya tiene una reserva en ese horario.');
        foreach ($teachers as $teacher) {
            $membership = DB::table('usuario_carrera')->where('usuario_id', $teacher)->where('carrera_id', $this->workflow->careerId())->lockForUpdate()->first();
            abort_unless($membership && $membership->activo && in_array((int) $membership->perfil_id, [1, 2, 5, 6, 7], true), 409, 'La membresía del evaluador cambió; revisa las asignaciones.');
        }
        foreach ($projects as $id) {
            $project = Project::where('activo', true)->findOrFail($id);
            $semester = $this->workflow->projectSemester($project, $period);
            $target = (int) $data['semestre'];
            abort_unless(in_array($target, [5, 6, 7, 8], true), 422, 'Etapa no evaluable en salas.');
            if ($semester !== $target) {
                $linked = $room ? DB::table('evaluaciones')->where('sala_id', $room->id)->where('proyecto_id', $id)->value('presentacion_autorizacion_id') : null;
                $approval = DB::table('autorizaciones_academicas')->where('carrera_id', $this->workflow->careerId())
                    ->where('periodo_id', $period)->where('proyecto_id', $id)->where('tipo', 'presentacion_semestre')
                    ->where('estado', 'aprobada')->where('semestre_destino', $target)->where('vigente_hasta', '>=', max(now(), $end))
                    ->where(fn ($q) => $q->whereNull('consumida_en')->when($linked, fn ($q) => $q->orWhere('id', $linked)))
                    ->lockForUpdate()->first();
                abort_unless($approval, 422, 'El proyecto requiere autorización vigente para esta etapa.');
            }
            $duplicate = DB::table('evaluaciones as e')->join('salas_evaluacion as s', 's.id', '=', 'e.sala_id')
                ->where('e.proyecto_id', $id)->where('s.id', '!=', $room?->id ?? 0)->where('s.estado', '!=', 'finalizada')
                ->where('e.estado', '!=', 'archivada')->lockForUpdate()->first();
            abort_if((bool) $duplicate, 409, 'El proyecto ya tiene una sala activa.');
            // A completed evaluation of the same stage requires a fresh authorization, not resetting old scores.
            $previous = DB::table('evaluaciones as e')->join('salas_evaluacion as s', 's.id', '=', 'e.sala_id')
                ->join('rubricas as r', 'r.id', '=', 's.rubrica_id')->where('e.proyecto_id', $id)
                ->where('s.id', '!=', $room?->id ?? 0)->where('r.semestre', $target)->lockForUpdate()->pluck('e.id');
            $alreadyInRoom = $room && DB::table('evaluaciones')->where('sala_id', $room->id)->where('proyecto_id', $id)->exists();
            if (!$alreadyInRoom && $previous->isNotEmpty()) {
                abort_unless($this->authorizations->valid('reintento', $id, $period)->whereIn('evaluacion_id', $previous)->where('vigente_hasta', '>=', $end)->exists(), 422, 'Se requiere autorización de nuevo intento.');
            }
        }
        return ['periodo_id' => $period, 'salon_id' => $salon->id, 'salon' => $salon->nombre];
    }
}
