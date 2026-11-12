<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\CareerContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pulso de cambios: la web y la app lo consultan cada pocos segundos y sólo recargan la sección cuya huella cambió
 * (solicitudes, propuestas, notificaciones…). Las huellas son resúmenes opacos: no revelan conteos ni datos.
 */
class LiveController extends Controller
{
    public function __invoke(CareerContext $context)
    {
        $user = auth('api')->user();
        $career = $context->careerId();
        // Varias pestañas del mismo usuario comparten el resultado unos segundos.
        $channels = Cache::remember("live:{$user->id}:{$user->perfil_id}:{$career}", 4, function () use ($user, $career) {
            $student = (int) $user->perfil_id === 3;
            $channels = [
                'notifications' => DB::table('notificaciones')->where('usuario_id', $user->id)
                    ->selectRaw('COUNT(*) total, MAX(id) last, SUM(leida_en IS NULL) unread')->first(),
                'proposals' => Project::where('tipo', 'propuesta')->selectRaw('COUNT(*) total, MAX(actualizado_en) changed')->first(),
                'projects' => Project::query()->selectRaw('COUNT(*) total, MAX(actualizado_en) changed')->first(),
                // Avisos del sistema: cambia al crear, editar, activar o quitar uno.
                'notices' => collect(\App\Models\CareerSetting::valueFor('system_notices', []))->map(fn ($notice) => [$notice['id'] ?? null, $notice['updated_at'] ?? null, $notice['active'] ?? true])->all(),
                'deliverables' => DB::table('entregas')->selectRaw('COUNT(*) total, MAX(entregado_en) last, COUNT(calificacion) graded')->first(),
            ];
            if ($user->canManageAcademics() || $user->isGeneralAdmin()) {
                $channels['registration_requests'] = DB::table('autoregistros_pendientes')->where('estado', '!=', 'codigo')
                    ->when(!$user->isGeneralAdmin(), fn ($query) => $query->where('carrera_id', $career))
                    ->selectRaw('SUM(estado = ?) pending, MAX(id) last, MAX(revisado_en) reviewed', ['por_aprobar'])->first();
            }
            if ($student) {
                $mine = DB::table('proyecto_integrantes')->where('usuario_id', $user->id)->pluck('proyecto_id');
                $channels['join_requests'] = DB::table('solicitudes_union_proyecto')
                    ->where(fn ($query) => $query->where('estudiante_id', $user->id)->orWhereIn('proyecto_id', $mine))
                    ->selectRaw('COUNT(*) total, MAX(id) last, MAX(respondida_en) answered, SUM(estado = ?) pending', ['pendiente'])->first();
            }

            return array_map(fn ($row) => substr(md5(json_encode($row)), 0, 12), $channels);
        });

        return response()->json(['channels' => $channels]);
    }
}
