# CheckRole

Fuente: [app/Http/Middleware/CheckRole.php](../../../../../../app/Http/Middleware/CheckRole.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Middleware;

use App\Support\CareerContext;
use Closure;
use Illuminate\Http\Request;

class CheckRole
{


}
```

## __construct

Visibilidad: `public`. Líneas originales 11–13.

```php
    public function __construct(private readonly CareerContext $careerContext)
    {
    }
```

## handle

Visibilidad: `public`. Líneas originales 15–31.

```php
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
```

## grantsForProfile

Visibilidad: `public`. Líneas originales 33–56.

```php
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
```
