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
                    if (now()->addDays(7)->toDateString() >= $period->fecha_fin) {
                        $key = "cierre:{$career}:{$period->id}:{$period->fecha_fin}";
                        if (DB::table('avisos_academicos')->insertOrIgnore(['clave' => $key, 'created_at' => now()])) {
                            foreach (DB::table('usuario_carrera')->where('carrera_id', $career)->where('activo', true)->whereIn('perfil_id', [1, 5, 6])->pluck('usuario_id') as $user) {
                                DB::table('notificaciones')->insert(['carrera_id' => $career, 'usuario_id' => $user, 'tipo' => 'periodo_por_cerrar',
                                    'titulo' => 'Revisar cierre del periodo', 'mensaje' => "El periodo {$period->nombre} termina el {$period->fecha_fin}. Revisa pendientes y promociones; el cierre requiere confirmación.", 'creada_en' => now()]);
                            }
                        }
                    }
                }
            }
            $expired = DB::table('autorizaciones_academicas')->whereIn('estado', ['solicitada', 'aprobada'])->whereNull('consumida_en')->where('vigente_hasta', '<', now())->lockForUpdate()->get();
            foreach ($expired as $row) {
                DB::table('autorizaciones_academicas')->where('id', $row->id)->update(['estado' => 'vencida', 'version' => $row->version + 1, 'updated_at' => now()]);
                DB::table('bitacora_academica')->insert(['carrera_id' => $row->carrera_id, 'accion' => 'autorizacion.vencida', 'entidad' => 'autorizacion', 'entidad_id' => (string) $row->id, 'antes' => json_encode($row), 'despues' => json_encode(['estado' => 'vencida']), 'creado_en' => now()]);
            }
        }, 3);
    }
}
