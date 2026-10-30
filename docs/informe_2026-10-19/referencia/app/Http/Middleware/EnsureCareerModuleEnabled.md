# EnsureCareerModuleEnabled

Fuente: [app/Http/Middleware/EnsureCareerModuleEnabled.php](../../../../../../app/Http/Middleware/EnsureCareerModuleEnabled.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Middleware;

use App\Models\CareerModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCareerModuleEnabled
{

}
```

## handle

Visibilidad: `public`. Líneas originales 12–24.

```php
    public function handle(Request $request, Closure $next): Response
    {
        $module = $this->moduleForPath($request->path());

        if ($module && !CareerModule::query()->where('modulo', $module)->where('habilitado', true)->exists()) {
            return response()->json([
                'error' => 'Este módulo no está habilitado para la carrera activa.',
                'module' => $module,
            ], 403);
        }

        return $next($request);
    }
```

## moduleForPath

Visibilidad: `private`. Líneas originales 26–51.

```php
    private function moduleForPath(string $path): ?string
    {
        $path = preg_replace('#^api/#', '', $path);
        if ($path === 'settings/current') {
            return null;
        }

        foreach ([
            'usuarios' => ['users', 'users-inactive'],
            'entregables' => ['deliverables', 'my-deliverables', 'teacher/deliverables-matrix'],
            'evaluaciones' => ['evaluations', 'evaluation-managers', 'evaluation-documents', 'student/evaluation-schedule'],
            'academico' => ['asignaturas', 'competencias', 'subject-groups', 'semester-management', 'career/setup', 'academic-operations', 'course-enrollments', 'residencies'],
            'repositorio' => ['repositorio', 'document-tags'],
            'configuracion' => ['settings', 'notices'],
            'reportes' => ['career/export'],
            'proyectos' => ['projects', 'my-projects', 'proposal'],
        ] as $module => $prefixes) {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $module;
                }
            }
        }

        return null;
    }
```
