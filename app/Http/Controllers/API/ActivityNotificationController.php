<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ActivityNotificationController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('notificaciones_actividad')) {
            return response()->json(['data' => [], 'unread_count' => 0]);
        }

        $userId = (string) auth('api')->id();
        $limit = min(max((int) $request->query('limit', 20), 1), 50);
        $query = ActivityNotification::with('actor:id,nombres,apellido_paterno,apellido_materno')
            ->where('usuario_id', $userId);

        return response()->json([
            'data' => (clone $query)->latest('creada_en')->limit($limit)->get(),
            'unread_count' => (clone $query)->whereNull('leida_en')->count(),
        ]);
    }

    public function markRead(ActivityNotification $notification)
    {
        $this->guardOwner($notification);
        if ($notification->leida_en === null) {
            $notification->update(['leida_en' => now()]);
        }

        return response()->json(['message' => 'Notificacion leida']);
    }

    public function markAllRead()
    {
        if (!Schema::hasTable('notificaciones_actividad')) {
            return response()->json(['message' => 'Notificaciones leidas']);
        }

        ActivityNotification::where('usuario_id', auth('api')->id())
            ->whereNull('leida_en')
            ->update(['leida_en' => now()]);

        return response()->json(['message' => 'Notificaciones leidas']);
    }

    private function guardOwner(ActivityNotification $notification): void
    {
        abort_unless((string) $notification->usuario_id === (string) auth('api')->id(), 403);
    }
}
