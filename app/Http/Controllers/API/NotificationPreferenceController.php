<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Avisos opcionales que corresponden al perfil activo. Los que requieren una acción no se pueden apagar. */
class NotificationPreferenceController extends Controller
{
    public function index()
    {
        $user = auth('api')->user();
        $muted = DB::table('preferencias_notificacion')->where('usuario_id', $user->id)->pluck('evento')->flip();

        return response()->json(['data' => collect($this->available())->map(fn ($event, $key) => ['key' => $key, 'label' => $event['label'], 'hint' => $event['hint'], 'enabled' => !$muted->has($key)])->values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['event' => 'required|string', 'enabled' => 'required|boolean']);
        abort_unless(array_key_exists($data['event'], $this->available()), 422, 'Ese aviso no se puede cambiar.');
        $key = ['usuario_id' => (string) auth('api')->id(), 'evento' => $data['event']];
        $data['enabled'] ? DB::table('preferencias_notificacion')->where($key)->delete()
            : DB::table('preferencias_notificacion')->insertOrIgnore($key + ['creada_en' => now()]);

        return response()->json(['message' => 'Preferencia guardada']);
    }

    private function available(): array
    {
        $profile = (int) app(CareerContext::class)->profileId();

        return array_filter(config('notifications.optional'), fn ($event) => in_array($profile, $event['profiles'], true) || in_array($profile, [1, 4], true));
    }
}
