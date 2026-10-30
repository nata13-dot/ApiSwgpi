# CompanyController

Fuente: [app/Http/Controllers/API/CompanyController.php](../../../app/Http/Controllers/API/CompanyController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/companies` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| PUT | `/api/companies/{company}/review` | [review](#review) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:project_manager |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{

}
```

## index

Visibilidad: `public`. Líneas originales 12–22.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `q`, `status`.

```php
    public function index(Request $request)
    {
        $query = Empresa::query()->withCount('projects');
        if (!$request->user('api')->canManageProjects()) $query->where('estado_validacion', 'aprobada');
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('nombre', 'like', "%{$term}%")->orWhere('rfc', 'like', "%{$term}%"));
        }
        if ($request->filled('status') && $request->user('api')->canManageProjects()) $query->where('estado_validacion', $request->status);
        return response()->json($query->orderBy('nombre')->limit(50)->get());
    }
```

## review

Visibilidad: `public`. Líneas originales 24–35.

```php
    public function review(Request $request, Empresa $company)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['aprobada', 'rechazada'])],
            'comment' => 'nullable|string|max:1000',
        ]);
        $company->update([
            'estado_validacion' => $validated['status'], 'comentario_validacion' => $validated['comment'] ?? null,
            'validada_por' => $request->user('api')->id, 'validada_en' => now(),
        ]);
        return response()->json(['message' => 'Empresa revisada.', 'company' => $company->fresh()]);
    }
```
