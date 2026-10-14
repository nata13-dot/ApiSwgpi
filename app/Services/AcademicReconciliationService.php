<?php
namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\SubjectGroup;
use App\Services\AcademicWorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicReconciliationService
{
    public function __construct(private AcademicWorkflowService $workflow) {}

    public function report(AcademicPeriod $period): array
    {
        $career = $this->workflow->careerId();
        $students = DB::table('usuario_carrera as m')->join('usuarios as u', 'u.id', '=', 'm.usuario_id')
            ->where('m.carrera_id', $career)->where('m.perfil_id', 3)->where('m.activo', true)->where('u.activo', true)
            ->orderBy('u.id')->get(['u.id', 'u.nombres']);
        $groups = SubjectGroup::where('periodo_id', $period->id)->where('activo', true)->orderBy('id')->get();
        $rows = $students->map(function ($student) use ($groups, $period, $career) {
            $legacy = DB::table('grupo_estudiantes')->where('estudiante_id', $student->id)->where('activo', true)
                ->whereIn('grupo_id', $groups->pluck('id'))->pluck('grupo_id')->map(fn ($id) => (int) $id)->all();
            $columns = null;
            if (Schema::hasColumn('usuarios', 'semestre') && Schema::hasColumn('usuarios', 'grupo')) {
                $columns = DB::table('usuarios')->where('id', $student->id)->first(['semestre', 'grupo']);
                if ($columns->semestre && $columns->grupo) {
                    $legacy = array_merge($legacy, $groups->filter(fn ($g) => (int) $g->semestre === (int) $columns->semestre && $g->grupo === $columns->grupo)->pluck('id')->all());
                }
            }
            $courseGroups = DB::table('curso_estudiantes as ce')->join('cursos as c', 'c.id', '=', 'ce.curso_id')
                ->where('ce.estudiante_id', $student->id)->where('ce.activo', true)->whereIn('c.grupo_id', $groups->pluck('id'))->pluck('c.grupo_id')->all();
            $legacy = array_merge($legacy, $courseGroups);
            $candidates = array_values(array_unique($legacy)); sort($candidates);
            $existing = DB::table('matriculas_academicas')->where(['carrera_id' => $career, 'periodo_id' => $period->id, 'estudiante_id' => $student->id])->first();
            return ['student_id' => $student->id, 'name' => $student->nombres, 'candidates' => $candidates, 'legacy' => $columns,
                'current' => $existing, 'group_id' => $existing?->grupo_id ?? (count($candidates) === 1 ? $candidates[0] : null),
                'issue' => count($candidates) > 1 ? 'Fuentes contradictorias' : (count($candidates) === 0 && !$existing ? 'Sin grupo verificable' : null)];
        })->all();
        $legacyExceptions = DB::table('autorizaciones_excepcionales as e')->join('proyectos as p', 'p.id', '=', 'e.proyecto_id')
            ->where('p.carrera_id', $career)->where('e.tipo', 'presentacion_semestre')->where('e.activa', true)
            ->get(['e.id', 'e.proyecto_id', 'e.usuario_id', 'e.valor', 'e.motivo']);
        $rooms = DB::table('salas_evaluacion')->where('carrera_id', $career)
            ->where(fn ($q) => $q->whereNull('periodo_id')->orWhereNull('salon_id'))->orderBy('id')->get()->map(function ($room) {
                $room->candidate_periods = DB::table('evaluaciones as e')->join('proyectos as p', 'p.id', '=', 'e.proyecto_id')
                    ->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')->where('e.sala_id', $room->id)->distinct()->pluck('g.periodo_id');
                return $room;
            });
        $report = ['rooms' => $rooms, 'period' => $period->id, 'career' => $career, 'students' => $rows, 'groups' => $groups, 'legacy_exceptions' => $legacyExceptions];
        return $report + ['snapshot' => hash('sha256', json_encode($report))];
    }

    public function apply(AcademicPeriod $period, array $data): void
    {
        $this->workflow->manager();
        DB::transaction(function () use ($period, $data) {
            $career = $this->workflow->careerId();
            DB::table('carreras')->where('id', $career)->lockForUpdate()->first();
            $state = DB::table('carrera_periodos')->where('carrera_id', $career)->where('periodo_id', $period->id)->first();
            abort_if($state && $state->estado !== 'preparacion', 409, 'La conciliación sólo se aplica durante preparación.');
            $report = $this->report($period);
            abort_unless(hash_equals($report['snapshot'], $data['snapshot']), 409, 'Los datos cambiaron. Recalcula la conciliación.');
            $students = collect($report['students'])->keyBy('student_id');
            abort_unless(count($data['resolutions']) === $students->count() && collect($data['resolutions'])->pluck('student_id')->unique()->count() === $students->count(), 422, 'Resuelve cada alumno del diagnóstico.');
            foreach ($data['resolutions'] as $row) {
                abort_unless($students->has($row['student_id']), 422);
                if (empty($row['group_id'])) {
                    abort_unless(trim($row['reason'] ?? '') !== '', 422, 'Explica la exclusión del alumno en este periodo.');
                    DB::table('matriculas_academicas')->where(['carrera_id' => $career, 'periodo_id' => $period->id, 'estudiante_id' => $row['student_id']])->update(['estado' => 'inactiva', 'version' => DB::raw('version + 1'), 'updated_at' => now()]);
                    continue;
                }
                $group = SubjectGroup::where('periodo_id', $period->id)->where('activo', true)->findOrFail($row['group_id']);
                DB::table('matriculas_academicas')->updateOrInsert(['carrera_id' => $career, 'periodo_id' => $period->id, 'estudiante_id' => $row['student_id']],
                    ['grupo_id' => $group->id, 'estado' => 'activa', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $roomRows = collect($data['room_resolutions'] ?? []);
            abort_unless($roomRows->count() === $report['rooms']->count() && $roomRows->pluck('room_id')->unique()->count() === $roomRows->count(), 422, 'Confirma el periodo y salón de cada sala histórica.');
            foreach ($roomRows as $resolution) {
                $oldRoom = $report['rooms']->firstWhere('id', $resolution['room_id']);
                abort_unless($oldRoom && trim($resolution['reason'] ?? '') !== '', 422, 'Identifica la sala y el motivo de conciliación.');
                abort_unless(DB::table('periodos_academicos')->where('id', $resolution['period_id'])->exists()
                    && DB::table('salones_institucionales')->where('id', $resolution['classroom_id'])->where('activo', true)->exists(), 422, 'Periodo o salón no disponible.');
                abort_if($oldRoom->periodo_id && (int) $oldRoom->periodo_id !== (int) $resolution['period_id'], 409, 'No se cambia el periodo de una sala ya conciliada.');
                DB::table('salas_evaluacion')->where('id', $oldRoom->id)->update(['periodo_id' => $resolution['period_id'], 'salon_id' => $resolution['classroom_id']]);
                $this->workflow->audit('sala.conciliada', 'sala', $oldRoom->id, $oldRoom, $resolution);
            }
            // Preserve originals; legacy exceptions are not silently granted broader project scope.
            abort_unless(empty($report['legacy_exceptions']->all()) || ($data['acknowledge_legacy_exceptions'] ?? false), 422, 'Las excepciones heredadas requieren revisión y nueva autorización explícita.');
            $projects = DB::table('proyectos')->where('carrera_id', $career)->whereIn('grupo_id', $report['groups']->pluck('id'))->get(['id', 'grupo_id']);
            foreach ($projects as $project) DB::table('proyecto_periodos')->insertOrIgnore([
                'carrera_id' => $career, 'periodo_id' => $period->id, 'proyecto_id' => $project->id, 'grupo_id' => $project->grupo_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('carrera_periodos')->updateOrInsert(['carrera_id' => $career, 'periodo_id' => $period->id],
                ['estado' => 'preparacion', 'conciliado' => true, 'created_at' => $state->created_at ?? now(), 'updated_at' => now()]);
            $this->workflow->audit('periodo.conciliado', 'periodo', $period->id, $report, $data);
        });
    }
}
