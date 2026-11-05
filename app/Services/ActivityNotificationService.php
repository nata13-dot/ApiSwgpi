<?php

namespace App\Services;

use App\Support\CareerContext;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Único punto de salida de los avisos: los guarda en la campana y los manda al teléfono, respetando lo que
 * cada persona apagó y el horario de silencio. Un fallo aquí nunca interrumpe la operación que lo originó.
 * El texto viaja por los servidores de Google: nunca debe llevar calificaciones, contraseñas ni códigos.
 */
class ActivityNotificationService
{
    public static function send(
        iterable $recipientIds,
        ?string $actorId,
        string $type,
        string $title,
        string $message,
        string $url,
        ?int $careerId = null
    ): void {
        if (!SchemaCache::hasTable('notificaciones')) {
            return;
        }

        try {
            $recipients = collect($recipientIds)
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->reject(fn ($id) => $actorId !== null && $id === (string) $actorId)
                ->unique()
                ->values();
            if ($recipients->isEmpty()) {
                return;
            }
            if (array_key_exists($type, config('notifications.optional', [])) && SchemaCache::hasTable('preferencias_notificacion')) {
                $muted = DB::table('preferencias_notificacion')->where('evento', $type)->whereIn('usuario_id', $recipients)->pluck('usuario_id')->map(fn ($id) => (string) $id);
                $recipients = $recipients->diff($muted)->values();
            }

            $career = $careerId ?? app(CareerContext::class)->careerId();
            foreach ($recipients as $recipientId) {
                DB::table('notificaciones')->insert([
                    'carrera_id' => $career, 'usuario_id' => $recipientId, 'actor_id' => $actorId, 'tipo' => $type,
                    'titulo' => mb_substr($title, 0, 180), 'mensaje' => $message, 'url' => $url, 'creada_en' => now(),
                ]);
            }
            app(PushNotificationService::class)->deliver($recipients->all(), $title, $message, $url, in_array($type, config('notifications.urgent', []), true));
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar una notificacion de actividad.', ['exception' => $e::class, 'message' => $e->getMessage()]);
        }
    }
}
