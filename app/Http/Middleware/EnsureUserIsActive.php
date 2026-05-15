<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('api')->user();
        if ($user && !$user->activo) {
            return response()->json(['error' => 'Cuenta desactivada'], 403);
        }

        return $next($request);
    }
}
