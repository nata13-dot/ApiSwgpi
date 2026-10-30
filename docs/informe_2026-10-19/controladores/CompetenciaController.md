# CompetenciaController

Fuente: [app/Http/Controllers/API/CompetenciaController.php](../../../app/Http/Controllers/API/CompetenciaController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/competencias` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/competencias` | [store](#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| GET\|HEAD | `/api/competencias/{id}` | [show](#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| PUT | `/api/competencias/{id}` | [update](#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| DELETE | `/api/competencias/{id}` | [destroy](#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\Competencia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetenciaController extends Controller
{





}
```

## index

Visibilidad: `public`. Líneas originales 13–26.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `asignatura_id`, `per_page`.

```php
    public function index(Request $request)
    {
        $query = Asignatura::query();

        if ($request->filled('asignatura_id')) {
            $query->where('id', $request->asignatura_id);
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $competencias = $query->orderBy('nombre')->paginate($perPage);
        $competencias->getCollection()->transform(fn (Asignatura $asignatura) => $this->fromAsignatura($asignatura));

        return response()->json($competencias);
    }
```

## store

Visibilidad: `public`. Líneas originales 28–45.

```php
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'asignatura_id' => 'required|exists:asignaturas,id',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            ]);

            return response()->json([
                'message' => 'En el esquema v2 las competencias se representan por asignaturas.',
                'competencia' => $this->fromAsignatura(Asignatura::findOrFail($validated['asignatura_id'])),
            ], 422);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## show

Visibilidad: `public`. Líneas originales 47–54.

```php
    public function show($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Competencia no encontrada'], 404);
        }
        return response()->json($this->fromAsignatura($asignatura));
    }
```

## update

Visibilidad: `public`. Líneas originales 56–78.

```php
    public function update(Request $request, $id)
    {
        try {
            $asignatura = Asignatura::find($id);
            if (!$asignatura) {
                return response()->json(['error' => 'Competencia no encontrada'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'asignatura_id' => 'required|exists:asignaturas,id',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            ]);

            return response()->json([
                'message' => 'En el esquema v2 las competencias se administran desde asignaturas.',
                'competencia' => $this->fromAsignatura($asignatura),
            ], 422);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## destroy

Visibilidad: `public`. Líneas originales 80–89.

```php
    public function destroy($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Competencia no encontrada'], 404);
        }
        return response()->json([
            'message' => 'En el esquema v2 las competencias se administran desde asignaturas.',
        ], 422);
    }
```

## fromAsignatura

Visibilidad: `private`. Líneas originales 91–107.

```php
    private function fromAsignatura(Asignatura $asignatura): array
    {
        return [
            'id' => $asignatura->id,
            'nombre' => $asignatura->nombre,
            'asignatura_id' => $asignatura->id,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'deliverables_count' => 0,
            'asignatura' => [
                'id' => $asignatura->id,
                'clave' => $asignatura->clave,
                'nombre' => $asignatura->nombre,
            ],
            'deliverables' => [],
        ];
    }
```
