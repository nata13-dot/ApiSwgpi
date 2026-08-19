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

        if ($user->globalProfileId() === 4) {
            $career = $careerId
                ? Career::query()->whereKey($careerId)->where('activa', true)->first()
                : Career::query()->where('activa', true)->orderBy('id')->first();
            $profileId = 4;
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

        $this->context->set($user, $career, (int) $profileId);
        $request->attributes->set('career_id', (int) $career->id);
        $request->attributes->set('career_profile_id', (int) $profileId);
        $request->attributes->set('career', $career);

        return $next($request);
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
