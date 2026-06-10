<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if ($user && !$user->activo) {
            return response()->json(['error' => 'Cuenta desactivada'], 403);
        }

        if ($user && !$this->rememberedSession() && $this->sessionExpiredByInactivity()) {
            try {
                JWTAuth::invalidate(JWTAuth::getToken());
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json(['error' => 'Sesion expirada por inactividad'], 401);
        }

        if ($user) {
            Cache::put($this->activityCacheKey(), now()->timestamp, now()->addMinutes($this->absoluteTokenTtlMinutes()));
        }

        return $next($request);
    }

    private function sessionExpiredByInactivity(): bool
    {
        $lastActivity = Cache::get($this->activityCacheKey());
        if (!$lastActivity) {
            return false;
        }

        return now()->timestamp - (int) $lastActivity > ($this->idleTimeoutMinutes() * 60);
    }

    private function activityCacheKey(): string
    {
        $token = (string) JWTAuth::getToken();

        return 'auth:last_activity:' . sha1($token);
    }

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

    private function idleTimeoutMinutes(): int
    {
        return max(1, (int) SystemSetting::valueFor('session_timeout_minutes', 60));
    }

    private function absoluteTokenTtlMinutes(): int
    {
        return max($this->idleTimeoutMinutes(), (int) config('jwt.absolute_ttl', 480));
    }
}
