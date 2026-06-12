<?php

namespace App\Services;

use App\Models\ActivityNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ActivityNotificationService
{
    public static function send(
        iterable $recipientIds,
        ?string $actorId,
        string $type,
        string $title,
        string $message,
        string $url
    ): void {
        if (!Schema::hasTable('notificaciones_actividad')) {
            return;
        }

        try {
            collect($recipientIds)
                ->map(fn ($id) => (string) $id)
                ->filter()
                ->reject(fn ($id) => $actorId !== null && $id === (string) $actorId)
                ->unique()
                ->each(fn ($recipientId) => ActivityNotification::create([
                    'usuario_id' => $recipientId,
                    'actor_id' => $actorId,
                    'tipo' => $type,
                    'titulo' => $title,
                    'mensaje' => $message,
                    'url' => $url,
                ]));
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar una notificacion de actividad.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
