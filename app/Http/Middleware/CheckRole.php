<?php

namespace App\Http\Middleware;

use App\Support\CareerContext;
use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function __construct(private readonly CareerContext $careerContext)
    {
    }

    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $profileId = $this->careerContext->profileId() ?? (int) $user->perfil_id;
        $allowedRoles = self::grantsForProfile($profileId);

        if (empty(array_intersect($roles, $allowedRoles))) {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        return $next($request);
    }

    public static function grantsForProfile(int $profileId): array
    {
        $roleMap = [
            1 => 'admin',
            2 => 'teacher',
            3 => 'student',
            4 => 'general_admin',
            5 => 'career_head',
            6 => 'career_head_assistant',
            7 => 'project_coordinator',
        ];
        $userRole = $roleMap[$profileId] ?? null;

        $grants = [
            'general_admin' => ['general_admin', 'user_governance', 'admin', 'teacher', 'academic_manager', 'project_manager'],
            'admin' => ['admin', 'teacher', 'academic_manager', 'project_manager'],
            'career_head' => ['career_head', 'admin', 'teacher', 'academic_manager', 'project_manager'],
            'career_head_assistant' => ['career_head_assistant', 'teacher', 'academic_manager', 'project_manager'],
            'project_coordinator' => ['project_coordinator', 'teacher', 'project_manager'],
            'teacher' => ['teacher'],
            'student' => ['student'],
        ];
        return $grants[$userRole] ?? [];
    }
}
