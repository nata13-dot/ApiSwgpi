<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = auth('api')->user();
        
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $roleMap = [1 => 'admin', 2 => 'teacher', 3 => 'student'];
        $userRole = $roleMap[$user->perfil_id] ?? null;

        if (!in_array($userRole, $roles)) {
            return response()->json(['error' => 'No tienes permisos'], 403);
        }

        return $next($request);
    }
}
