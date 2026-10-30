<?php
namespace App\Services;

use App\Models\AcademicPeriod;
use Illuminate\Support\Facades\DB;

class AcademicPeriodLifecycleService
{
    public function __construct(private AcademicWorkflowService $workflow) {}

    public function transition(AcademicPeriod $period, string $state): void
    {
        $this->workflow->manager();
        $this->workflow->ready();
        DB::transaction(function () use ($period, $state) {
            $career = $this->workflow->careerId();
            DB::table('carreras')->where('id', $career)->lockForUpdate()->first();
            $key = ['carrera_id' => $career, 'periodo_id' => $period->id];
            $old = DB::table('carrera_periodos')->where($key)->lockForUpdate()->first();
            abort_unless($old && $old->conciliado, 409, 'Primero concilia los datos de este periodo y carrera.');
            if ($old->estado === $state) return;
            $allowed = ['preparacion' => ['abierto'], 'abierto' => ['en_cierre'], 'en_cierre' => ['abierto', 'cerrado'], 'cerrado' => []];
            abort_unless(in_array($state, $allowed[$old->estado] ?? [], true), 409, 'Transición de periodo no permitida.');
            if ($state === 'abierto') {
                abort_if(DB::table('carrera_periodos')->where('carrera_id', $career)->where('periodo_id', '!=', $period->id)
                    ->whereIn('estado', ['abierto', 'en_cierre'])->exists(), 409, 'Cierra el periodo anterior de esta carrera.');
                abort_unless($period->fecha_inicio && $period->fecha_fin && today()->betweenIncluded($period->fecha_inicio, $period->fecha_fin), 422, 'El periodo no está dentro de sus fechas institucionales.');
            }
            if ($state === 'cerrado') {
                $pending = \App\Support\CareerScope::constrain(DB::table('salas_evaluacion'), 'salas_evaluacion', $career)->where(fn ($q) => $q->where('periodo_id', $period->id)->orWhereNull('periodo_id'))->where('estado', '!=', 'finalizada')->exists()
                    || \App\Support\CareerScope::constrain(DB::table('residencias'), 'residencias', $career)->where('periodo_id', $period->id)->where('estado', '!=', 'cerrada')->exists()
                    || \App\Support\CareerScope::constrainOptional(DB::table('autorizaciones_academicas'), 'autorizaciones_academicas', $career)->where('periodo_id', $period->id)->where('estado', 'solicitada')->exists();
                $pending = $pending || DB::table('entregas as e')->join('entregables as d', 'd.id', '=', 'e.entregable_id')
                    ->join('cursos as c', 'c.id', '=', 'd.curso_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
                    ->where('g.carrera_id', $career)->where('g.periodo_id', $period->id)->whereNull('e.calificacion')->exists();
                abort_if($pending, 409, 'Resuelve las salas, entregas sin revisión, residencias y solicitudes pendientes antes del cierre.');
            }
            DB::table('carrera_periodos')->where($key)->update(['estado' => $state, 'updated_at' => now()]);
            AcademicWorkflowService::forgetPeriods();
            $this->workflow->audit('periodo.'.$state, 'periodo', $period->id, $old, ['estado' => $state]);
        }, 3);
    }
}
