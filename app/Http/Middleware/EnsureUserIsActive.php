<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if ($user && !$user->activo) {
            return response()->json(['error' => 'Cuenta desactivada'], 403);
        }

        // El token de la app no vence: lo que decide si sigue valiendo es su registro.
        if ($user && ($appSession = \App\Services\AppSessionService::current())
            && !app(\App\Services\AppSessionService::class)->isActive($appSession, (string) $user->getAuthIdentifier())) {
            return response()->json(['error' => 'La sesión se cerró. Inicia sesión de nuevo.'], 401);
        }

        // "Ver como" del administrador general: el token sólo puede leer.
        $readOnly = $user && $this->readOnlyToken();
        if ($readOnly && !$request->isMethodSafe() && !$request->is('api/auth/heartbeat', 'api/auth/logout')) {
            return response()->json([
                'error' => 'Estás en modo "ver como" (sólo lectura). Vuelve a tu sesión de administrador general para hacer cambios.',
                'read_only' => true,
            ], 403);
        }

        // Con la contraseña temporal (matrícula) sólo se permite consultar la sesión y cambiarla.
        if ($user && !$readOnly && $user->debe_cambiar_contrasena && !$this->allowedWithTemporaryPassword($request)) {
            return response()->json([
                'error' => 'Debes cambiar tu contraseña temporal antes de continuar.',
                'requires_password_change' => true,
            ], 428);
        }

        if ($user) {
            try {
                if (!$this->rememberedSession() && $this->sessionExpiredByInactivity()) {
                    try {
                        JWTAuth::invalidate(JWTAuth::getToken());
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    return response()->json(['error' => 'Sesion expirada por inactividad'], 401);
                }

                $this->activityCache()->put(
                    $this->activityCacheKey(),
                    now()->timestamp,
                    now()->addMinutes($this->absoluteTokenTtlMinutes())
                );
            } catch (\Throwable $e) {
                try {
                    Log::warning('No se pudo actualizar la actividad de la sesion.', [
                        'user_id' => $user->getAuthIdentifier(),
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                    // El seguimiento de actividad no debe bloquear una peticion autenticada.
                }
            }
        }

        return $next($request);
    }

    private function readOnlyToken(): bool
    {
        try {
            return (bool) JWTAuth::parseToken()->getPayload()->get('read_only');
        } catch (\Throwable) {
            return false;
        }
    }

    private function allowedWithTemporaryPassword(Request $request): bool
    {
        return $request->is('api/auth/me', 'api/auth/heartbeat', 'api/auth/logout', 'api/auth/careers', 'api/auth/switch-career', 'api/auth/password/change-initial', 'api/settings/current');
    }

    private function sessionExpiredByInactivity(): bool
    {
        return self::idleSince($this->activityCache()->get($this->activityCacheKey()));
    }

    /** También lo usa la renovación: un token inactivo no puede cambiarse por uno nuevo. */
    public static function tokenIsIdle(string $token): bool
    {
        return self::idleSince(Cache::store(config('auth.activity_cache_store', 'file'))->get('auth:last_activity:'.sha1($token)));
    }

    private static function idleSince(mixed $lastActivity): bool
    {
        if (!$lastActivity) {
            return false;
        }

        return now()->timestamp - (int) $lastActivity > (max(1, (int) SystemSetting::valueFor('session_timeout_minutes', 60)) * 60);
    }

    private function activityCacheKey(): string
    {
        $token = (string) JWTAuth::getToken();

        return 'auth:last_activity:' . sha1($token);
    }

    /** Solo la app móvil conserva la sesión. No se confía en lo que indique el cliente. */
    private function rememberedSession(): bool
    {
        return \App\Services\AppSessionService::current() !== null;
    }

    private function idleTimeoutMinutes(): int
    {
        return max(1, (int) SystemSetting::valueFor('session_timeout_minutes', 60));
    }

    private function absoluteTokenTtlMinutes(): int
    {
        return max($this->idleTimeoutMinutes(), (int) config('jwt.absolute_ttl', 480));
    }

    private function activityCache(): Repository
    {
        return Cache::store(config('auth.activity_cache_store', 'file'));
    }
}
