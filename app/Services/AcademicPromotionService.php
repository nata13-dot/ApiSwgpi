<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\SubjectGroup;
use Illuminate\Support\Facades\DB;

class AcademicPromotionService
{
    public function __construct(private AcademicWorkflowService $workflow) {}

    public function preview(AcademicPeriod $destination): array
    {
        $career = $this->workflow->careerId();
        $source = $this->workflow->period();
        abort_unless($source && $source->id !== $destination->id && $destination->fecha_inicio > $source->fecha_inicio, 422, 'Selecciona un periodo posterior al periodo abierto.');
        $rows = DB::table('matriculas_academicas as m')->join('grupos_academicos as g', 'g.id', '=', 'm.grupo_id')
            ->where('m.carrera_id', $career)->where('m.periodo_id', $source->id)->where('m.estado', 'activa')
            ->orderBy('m.estudiante_id')->select('m.*', 'g.semestre', 'g.clave_grupo')->get();
        $groups = SubjectGroup::where('periodo_id', $destination->id)->where('activo', true)->orderBy('id')->get();
        $movements = $rows->map(function ($row) use ($groups, $destination) {
            $next = min(9, (int) $row->semestre + 1);
            $group = $groups->first(fn ($g) => (int) $g->semestre === $next && $g->grupo === $row->clave_grupo);
            $existing = DB::table('matriculas_academicas')->where('carrera_id', $row->carrera_id)
                ->where('periodo_id', $destination->id)->where('estudiante_id', $row->estudiante_id)->first();
            $projectIds = DB::table('proyecto_integrantes')->where('usuario_id', $row->estudiante_id)->whereIn('rol', ['lider', 'integrante'])->pluck('proyecto_id');
            $pendingDeliveries = DB::table('entregas as e')->join('entregables as d', 'd.id', '=', 'e.entregable_id')->join('cursos as c', 'c.id', '=', 'd.curso_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
                ->where('g.periodo_id', $row->periodo_id)->where('c.carrera_id', $row->carrera_id)->whereIn('e.proyecto_id', $projectIds)->whereNull('e.calificacion')->count();
            $pendingRooms = DB::table('evaluaciones as e')->join('salas_evaluacion as s', 's.id', '=', 'e.sala_id')->where('s.periodo_id', $row->periodo_id)->where('s.carrera_id', $row->carrera_id)->whereIn('e.proyecto_id', $projectIds)->where('e.estado', '!=', 'archivada')->where('s.estado', '!=', 'finalizada')->count();
            $pendingResidencies = DB::table('residencias')->where('carrera_id', $row->carrera_id)->where('periodo_id', $row->periodo_id)->where('estudiante_id', $row->estudiante_id)->where('estado', 'abierta')->count();
            $pending = array_filter([$existing ? 'Ya existe matrícula destino' : ($group ? null : 'Selecciona grupo destino'), $pendingDeliveries ? $pendingDeliveries.' entregas pendientes' : null, $pendingRooms ? $pendingRooms.' evaluaciones pendientes' : null, $pendingResidencies ? 'Residencia abierta' : null]);
            return ['student_id' => $row->estudiante_id, 'source_id' => $row->id, 'source_version' => $row->version,
                'from' => (int) $row->semestre, 'to' => $next, 'group_id' => $group?->id,
                'action' => (int) $row->semestre === 9 ? 'permanencia' : 'avance', 'reason' => '',
                'pending' => implode('; ', $pending) ?: null];
        })->all();
        $recorded = DB::table('movimientos_academicos')->where('carrera_id', $career)->where('origen_id', $source->id)->where('destino_id', $destination->id)->orderBy('estudiante_id')->get();
        $snapshot = ['movements' => $movements, 'recorded' => $recorded, 'source' => $source->id, 'destination' => $destination->id, 'career' => $career,
            'rows' => $rows->toArray(), 'groups' => $groups->map->only(['id', 'semestre', 'grupo', 'activo'])->all()];
        return ['period' => $destination, 'source_period_id' => $source->id, 'snapshot' => hash('sha256', json_encode($snapshot)),
            'groups' => $groups, 'movements' => $movements];
    }

    public function confirm(AcademicPeriod $destination, array $data): array
    {
        $this->workflow->manager();
        return DB::transaction(function () use ($destination, $data) {
            $career = $this->workflow->careerId();
            DB::table('carreras')->where('id', $career)->lockForUpdate()->first();
            $hash = hash('sha256', json_encode($data));
            $previous = DB::table('operaciones_academicas')->where('id', $data['operation_id'])->first();
            if ($previous) {
                abort_unless((int) $previous->carrera_id === $career && $previous->huella === $hash, 409, 'Identificador de operación ya utilizado.');
                return json_decode($previous->resultado, true);
            }
            $preview = $this->preview($destination);
            abort_unless(hash_equals($preview['snapshot'], $data['snapshot']), 409, 'La vista previa cambió. Recalcula antes de confirmar.');
            $source = $preview['source_period_id'];
            DB::table('carrera_periodos')->where('carrera_id', $career)->where('periodo_id', $source)->lockForUpdate()->first();
            abort_if(DB::table('carrera_periodos')->where('carrera_id', $career)->where('periodo_id', $destination->id)->where('estado', '!=', 'preparacion')->exists(), 409, 'El destino debe estar en preparación.');
            $expected = collect($preview['movements'])->keyBy('student_id');
            abort_unless(count($data['movements']) === $expected->count() && collect($data['movements'])->pluck('student_id')->unique()->count() === $expected->count(), 422, 'El lote debe resolver cada alumno exactamente una vez.');
            $updated = 0;
            foreach ($data['movements'] as $move) {
                $old = $expected->get($move['student_id']);
                abort_unless($old, 422, 'Alumno ajeno al lote.');
                abort_unless(trim($move['reason'] ?? '') !== '', 422, 'Cada movimiento requiere motivo.');
                $movementKey = ['carrera_id' => $career, 'origen_id' => $source, 'destino_id' => $destination->id, 'estudiante_id' => $move['student_id']];
                abort_if(DB::table('movimientos_academicos')->where($movementKey)->exists(), 409, 'El movimiento de este alumno ya fue confirmado.');
                if ($move['action'] !== 'salida') {
                    $group = SubjectGroup::where('periodo_id', $destination->id)->where('activo', true)->findOrFail($move['group_id']);
                    $semester = $move['action'] === 'permanencia' ? $old['from'] : min(9, $old['from'] + 1);
                    abort_unless((int) $group->semestre === $semester, 422, 'El grupo destino no corresponde al movimiento.');
                    abort_if(DB::table('matriculas_academicas')->where(['carrera_id' => $career, 'periodo_id' => $destination->id, 'estudiante_id' => $move['student_id']])->exists(), 409, 'El alumno ya tiene matrícula destino.');
                    DB::table('matriculas_academicas')->insert(['carrera_id' => $career, 'periodo_id' => $destination->id,
                        'estudiante_id' => $move['student_id'], 'grupo_id' => $group->id, 'estado' => 'activa', 'version' => 1,
                        'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('movimientos_academicos')->insert($movementKey + ['operacion_id' => $data['operation_id'], 'accion' => $move['action'], 'motivo' => $move['reason'], 'created_at' => now(), 'updated_at' => now()]);
                // Historical source remains readable. Moving does not rewrite previous project groups.
                $this->workflow->audit('promocion.'.$move['action'], 'matricula', $old['source_id'], $old, $move);
                $updated++;
            }
            DB::table('carrera_periodos')->insertOrIgnore(['carrera_id' => $career, 'periodo_id' => $destination->id, 'estado' => 'preparacion', 'conciliado' => false, 'created_at' => now(), 'updated_at' => now()]);
            $result = ['reviewed' => $expected->count(), 'updated' => $updated, 'operation_id' => $data['operation_id']];
            DB::table('operaciones_academicas')->insert(['id' => $data['operation_id'], 'carrera_id' => $career,
                'periodo_id' => $destination->id, 'tipo' => 'promocion', 'actor_id' => auth('api')->id(),
                'huella' => $hash, 'resultado' => json_encode($result), 'created_at' => now(), 'updated_at' => now()]);
            return $result;
        }, 3);
    }
}
