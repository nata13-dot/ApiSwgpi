<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class AcademicMaintenanceService
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('recursos_agenda')->insertOrIgnore(['id' => 'scheduler:academico']);
            DB::table('recursos_agenda')->where('id', 'scheduler:academico')->lockForUpdate()->first();
            foreach (DB::table('carreras')->where('activa', true)->pluck('id') as $career) {
                foreach (DB::table('periodos_academicos')->whereDate('fecha_fin', '>=', today())->get() as $period) {
                    DB::table('carrera_periodos')->insertOrIgnore(['carrera_id' => $career, 'periodo_id' => $period->id, 'estado' => 'preparacion', 'conciliado' => false, 'created_at' => now(), 'updated_at' => now()]);
                    AcademicWorkflowService::forgetPeriods();
                    if (now()->addDays(7)->toDateString() >= $period->fecha_fin) {
                        $key = "cierre:{$career}:{$period->id}:{$period->fecha_fin}";
                        if (DB::table('avisos_academicos')->insertOrIgnore(['clave' => $key, 'created_at' => now()])) {
                            ActivityNotificationService::send(DB::table('usuario_carrera')->where('carrera_id', $career)->where('activo', true)->whereIn('perfil_id', [1, 5, 6])->pluck('usuario_id'), null, 'periodo_por_cerrar', 'Revisar cierre del periodo', "El periodo {$period->nombre} termina el {$period->fecha_fin}. Revisa pendientes y promociones; el cierre requiere confirmación.", '/semesters', $career);
                        }
                    }
                }
            }
            $this->remindIrregularCheck();
            $expired = DB::table('autorizaciones_academicas')->whereIn('estado', ['solicitada', 'aprobada'])->whereNull('consumida_en')->where('vigente_hasta', '<', now())->lockForUpdate()->get();
            foreach ($expired as $row) {
                DB::table('autorizaciones_academicas')->where('id', $row->id)->update(['estado' => 'vencida', 'version' => $row->version + 1, 'updated_at' => now()]);
                DB::table('bitacora_academica')->insert(['carrera_id' => \App\Support\CareerScope::of('autorizaciones_academicas', $row->id), 'accion' => 'autorizacion.vencida', 'entidad' => 'autorizacion', 'entidad_id' => (string) $row->id, 'antes' => json_encode($row), 'despues' => json_encode(['estado' => 'vencida']), 'creado_en' => now()]);
            }
        }, 3);
    }

    /** Cada semana de las primeras 8 del periodo abierto se avisa a la jefatura que revise alumnos irregulares. */
    private function remindIrregularCheck(): void
    {
        $weeks = \App\Http\Controllers\API\SemesterManagementController::IRREGULAR_CHECK_WEEKS;
        $open = DB::table('carrera_periodos as cp')->join('periodos_academicos as p', 'p.id', '=', 'cp.periodo_id')
            ->whereIn('cp.estado', ['abierto', 'en_cierre'])->whereDate('p.fecha_inicio', '<=', today())
            ->get(['cp.carrera_id', 'p.id', 'p.nombre', 'p.fecha_inicio']);
        foreach ($open as $period) {
            $week = intdiv((int) \Illuminate\Support\Carbon::parse($period->fecha_inicio)->diffInDays(today()), 7) + 1;
            if ($week > $weeks) continue;
            $answered = DB::table('revisiones_irregulares')->where(['carrera_id' => $period->carrera_id, 'periodo_id' => $period->id, 'semana' => $week])->exists();
            if ($answered || !DB::table('avisos_academicos')->insertOrIgnore(['clave' => "irregulares:{$period->carrera_id}:{$period->id}:{$week}", 'created_at' => now()])) continue;
            ActivityNotificationService::send(DB::table('usuario_carrera')->where('carrera_id', $period->carrera_id)->where('activo', true)->whereIn('perfil_id', [1, 5, 6])->pluck('usuario_id'), null, 'revision_irregulares', "Semana {$week} de {$weeks}: alumnos en otro semestre", "¿Hay alumnos que por materias reprobadas cursen un semestre distinto al que les corresponde en {$period->nombre}? Responde desde el panel.", '/semesters', (int) $period->carrera_id);
        }
    }
}
