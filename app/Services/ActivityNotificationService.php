<?php

namespace App\Services;

use App\Models\ActivityNotification;

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
    }
}
