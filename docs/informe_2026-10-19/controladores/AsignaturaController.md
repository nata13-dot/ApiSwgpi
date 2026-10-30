# AsignaturaController

Fuente: [app/Http/Controllers/API/AsignaturaController.php](../../../app/Http/Controllers/API/AsignaturaController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/asignaturas` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/asignaturas` | [store](#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| GET\|HEAD | `/api/asignaturas/{id}` | [show](#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| PUT | `/api/asignaturas/{id}` | [update](#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |
| DELETE | `/api/asignaturas/{id}` | [destroy](#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin,academic_manager |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\CareerContext;

class AsignaturaController extends Controller
{




}
```

## index

Visibilidad: `public`. Líneas originales 14–21.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `per_page`.

```php
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 15), 100);
        $asignaturas = Asignatura::orderBy('nombre')->paginate($perPage);
        $asignaturas->getCollection()->each(fn (Asignatura $asignatura) => $asignatura->setAttribute('competencias_count', 1));

        return response()->json($asignaturas);
    }
```

## store

Visibilidad: `public`. Líneas originales 23–41.

```php
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'clave' => [
                    'nullable', 'string', 'max:50',
                    Rule::unique('asignaturas', 'clave')
                        ->where('carrera_id', app(CareerContext::class)->careerId()),
                ],
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
            ]);

            $asignatura = Asignatura::create($validated);
            return response()->json(['message' => 'Asignatura creada', 'asignatura' => $asignatura], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## show

Visibilidad: `public`. Líneas originales 43–60.

```php
    public function show($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Asignatura no encontrada'], 404);
        }
        $asignatura->setAttribute('competencias_count', 1);
        $asignatura->setRelation('competencias', collect([[
            'id' => $asignatura->id,
            'nombre' => $asignatura->nombre,
            'asignatura_id' => $asignatura->id,
            'fecha_inicio' => null,
            'fecha_fin' => null,
            'deliverables' => [],
        ]]));

        return response()->json($asignatura);
    }
```

## update

Visibilidad: `public`. Líneas originales 62–86.

```php
    public function update(Request $request, $id)
    {
        try {
            $asignatura = Asignatura::find($id);
            if (!$asignatura) {
                return response()->json(['error' => 'Asignatura no encontrada'], 404);
            }

            $validated = $request->validate([
                'clave' => [
                    'nullable', 'string', 'max:50',
                    Rule::unique('asignaturas', 'clave')
                        ->where('carrera_id', app(CareerContext::class)->careerId())
                        ->ignore($asignatura->id),
                ],
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
            ]);

            $asignatura->update($validated);
            return response()->json(['message' => 'Asignatura actualizada', 'asignatura' => $asignatura]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## destroy

Visibilidad: `public`. Líneas originales 88–96.

```php
    public function destroy($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Asignatura no encontrada'], 404);
        }
        $asignatura->delete();
        return response()->json(['message' => 'Asignatura eliminada']);
    }
```
