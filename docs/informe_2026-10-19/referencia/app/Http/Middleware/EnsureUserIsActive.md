# EnsureUserIsActive

Fuente: [app/Http/Middleware/EnsureUserIsActive.php](../../../../../../app/Http/Middleware/EnsureUserIsActive.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
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






}
```

## handle

Visibilidad: `public`. Líneas originales 15–53.

```php
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if ($user && !$user->activo) {
            return response()->json(['error' => 'Cuenta desactivada'], 403);
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
```

## sessionExpiredByInactivity

Visibilidad: `private`. Líneas originales 55–63.

```php
    private function sessionExpiredByInactivity(): bool
    {
        $lastActivity = $this->activityCache()->get($this->activityCacheKey());
        if (!$lastActivity) {
            return false;
        }

        return now()->timestamp - (int) $lastActivity > ($this->idleTimeoutMinutes() * 60);
    }
```

## activityCacheKey

Visibilidad: `private`. Líneas originales 65–70.

```php
    private function activityCacheKey(): string
    {
        $token = (string) JWTAuth::getToken();

        return 'auth:last_activity:' . sha1($token);
    }
```

## rememberedSession

Visibilidad: `private`. Líneas originales 72–83.

```php
    private function rememberedSession(): bool
    {
        if (request()->boolean('remember_session') || request()->header('X-SGPI-Remember') === '1') {
            return true;
        }

        try {
            return (bool) JWTAuth::parseToken()->getPayload()->get('remember');
        } catch (\Throwable $e) {
            return false;
        }
    }
```

## idleTimeoutMinutes

Visibilidad: `private`. Líneas originales 85–88.

```php
    private function idleTimeoutMinutes(): int
    {
        return max(1, (int) SystemSetting::valueFor('session_timeout_minutes', 60));
    }
```

## absoluteTokenTtlMinutes

Visibilidad: `private`. Líneas originales 90–93.

```php
    private function absoluteTokenTtlMinutes(): int
    {
        return max($this->idleTimeoutMinutes(), (int) config('jwt.absolute_ttl', 480));
    }
```

## activityCache

Visibilidad: `private`. Líneas originales 95–98.

```php
    private function activityCache(): Repository
    {
        return Cache::store(config('auth.activity_cache_store', 'file'));
    }
```
