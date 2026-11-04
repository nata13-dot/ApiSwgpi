<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Sesiones de la app móvil: duran hasta cerrarlas. El token no vence, pero se valida contra este registro.
 * Se anulan al cerrar sesión y al cambiar la contraseña; desactivar la cuenta ya impide entrar por sí solo.
 */
class AppSessionService
{
    /** Diez años: el token exige una fecha de vencimiento, pero en la práctica la sesión no expira. */
    public const TOKEN_TTL_MINUTES = 5256000;

    public function create(string $userId): string
    {
        $id = (string) Str::uuid();
        DB::table('sesiones_app')->insert(['id' => $id, 'usuario_id' => $userId, 'creada_en' => now(), 'ultimo_uso_en' => now()]);

        return $id;
    }

    public function isActive(string $id, string $userId): bool
    {
        $session = DB::table('sesiones_app')->where('id', $id)->where('usuario_id', $userId)->whereNull('revocada_en')->first(['ultimo_uso_en']);
        if (!$session) {
            return false;
        }
        // Una escritura al día basta para saber qué sesiones siguen en uso.
        if (!$session->ultimo_uso_en || now()->diffInHours($session->ultimo_uso_en, true) >= 24) {
            DB::table('sesiones_app')->where('id', $id)->update(['ultimo_uso_en' => now()]);
        }

        return true;
    }

    public function revoke(string $id): void
    {
        DB::table('sesiones_app')->where('id', $id)->whereNull('revocada_en')->update(['revocada_en' => now()]);
    }

    /** Al cambiar la contraseña se cierran las demás sesiones; quien la cambia desde la app sigue dentro. */
    public function revokeAllForUser(string $userId, ?string $exceptId = null): void
    {
        DB::table('sesiones_app')->where('usuario_id', $userId)->whereNull('revocada_en')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->update(['revocada_en' => now()]);
    }

    /** Sesión de app del token de la petición, si la tiene. */
    public static function current(): ?string
    {
        try {
            return JWTAuth::parseToken()->getPayload()->get('app_session') ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
