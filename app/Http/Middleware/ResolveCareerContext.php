<?php

namespace App\Http\Middleware;

use App\Models\Career;
use App\Support\CareerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResolveCareerContext
{
    public function __construct(private readonly CareerContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $careerId = $this->careerIdFromToken();
        $membership = null;

        $simulated = null;
        if ($user->globalProfileId() === 4) {
            $career = $careerId
                ? Career::query()->whereKey($careerId)->where('activa', true)->first()
                : Career::query()->where('activa', true)->orderBy('id')->first();
            $profileId = 4;
            // Simulación de nivel: sólo con un token de sólo lectura emitido para el propio administrador.
            $simulated = $this->simulatedProfileFromToken();
            if ($simulated) {
                $profileId = $simulated;
            }
        } else {
            $memberships = $user->careerMemberships()
                ->where('activo', true)
                ->whereHas('career', fn ($query) => $query->where('activa', true));

            $membership = $careerId
                ? (clone $memberships)->where('carrera_id', $careerId)->first()
                : (clone $memberships)->orderByDesc('es_principal')->orderBy('id')->first();

            $career = $membership?->career;
            $profileId = $membership?->perfil_id;
        }

        if (!$career || !$profileId) {
            return response()->json([
                'error' => 'No tienes acceso a la carrera seleccionada.',
                'code' => 'CAREER_ACCESS_DENIED',
            ], 403);
        }

        $this->context->set($user, $career, (int) $profileId, $simulated);
        // Aislamiento del administrador general: fuera de una carrera (sin «entrar») sólo consulta; para modificar
        // datos de una carrera debe entrar a ella. Lo institucional (/admin, cuenta propia) no requiere entrar.
        if ($user->globalProfileId() === 4 && !$simulated) {
            $entered = $this->claim('career_entered') === true;
            $this->context->setEntered($entered);
            if (!$entered && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && !$this->institutional($request)) {
                return response()->json([
                    'error' => 'Para modificar datos de una carrera, entra primero a esa carrera desde la administración general.',
                    'code' => 'CAREER_NOT_ENTERED',
                ], 403);
            }
        }
        $request->attributes->set('career_id', (int) $career->id);
        $request->attributes->set('career_profile_id', (int) $profileId);
        $request->attributes->set('career', $career);

        return $next($request);
    }

    private function simulatedProfileFromToken(): ?int
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $profile = (int) $payload->get('simulated_profile');

            return $payload->get('read_only') && in_array($profile, \App\Support\Profiles::ASSIGNABLE, true) ? $profile : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function claim(string $name): mixed
    {
        try {
            return JWTAuth::parseToken()->getPayload()->get($name);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Rutas que no son de una carrera: la administración institucional y la cuenta propia. */
    private function institutional(Request $request): bool
    {
        return $request->is('api/admin/*', 'api/auth/*', 'api/appearance', 'api/notifications/*', 'api/activity-notifications', 'api/activity-notifications/*',
            'api/devices/*', 'api/profile', 'api/profile/*', 'api/users/*/general-admin');
    }

    private function careerIdFromToken(): ?int
    {
        try {
            $value = JWTAuth::parseToken()->getPayload()->get('career_id');

            return $value === null ? null : (int) $value;
        } catch (\Throwable) {
            return null;
        }
    }
}
