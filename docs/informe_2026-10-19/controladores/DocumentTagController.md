# DocumentTagController

Fuente: [app/Http/Controllers/API/DocumentTagController.php](../../../app/Http/Controllers/API/DocumentTagController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/document-tags` | [index](#index) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| POST | `/api/document-tags` | [store](#store) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| GET\|HEAD | `/api/document-tags/{id}` | [show](#show) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| PUT | `/api/document-tags/{id}` | [update](#update) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |
| DELETE | `/api/document-tags/{id}` | [destroy](#destroy) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen, CheckRole:admin |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTag;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentTagController extends Controller
{




}
```

## index

Visibilidad: `public`. Líneas originales 12–35.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `per_page`, `q`.

```php
    public function index(Request $request)
    {
        $query = DocumentTag::query()
            ->where('activo', true)
            ->withCount(['deliverables', 'repositoryDocuments'])
            ->orderBy('nombre');

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $query->where(function ($subquery) use ($term) {
                $subquery->where('nombre', 'like', "%{$term}%")
                    ->orWhere('descripcion', 'like', "%{$term}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 5), 50);
        $tags = $query->paginate($perPage);
        $tags->getCollection()->transform(function (DocumentTag $tag) {
            $tag->documents_count = (int) $tag->deliverables_count + (int) $tag->repository_documents_count;
            return $tag;
        });

        return response()->json($tags);
    }
```

## store

Visibilidad: `public`. Líneas originales 37–51.

```php
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:100|unique:etiquetas,nombre',
                'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'descripcion' => 'nullable|string|max:1000',
            ]);

            $tag = DocumentTag::create($validated);
            return response()->json(['message' => 'Etiqueta creada', 'tag' => $tag], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## show

Visibilidad: `public`. Líneas originales 53–60.

```php
    public function show($id)
    {
        $tag = DocumentTag::with('deliverables')->find($id);
        if (!$tag) {
            return response()->json(['error' => 'Etiqueta no encontrada'], 404);
        }
        return response()->json($tag);
    }
```

## update

Visibilidad: `public`. Líneas originales 62–82.

```php
    public function update(Request $request, $id)
    {
        try {
            $tag = DocumentTag::find($id);
            if (!$tag) {
                return response()->json(['error' => 'Etiqueta no encontrada'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:100|unique:etiquetas,nombre,' . $tag->id,
                'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'descripcion' => 'nullable|string|max:1000',
                'activo' => 'nullable|boolean',
            ]);

            $tag->update($validated);
            return response()->json(['message' => 'Etiqueta actualizada', 'tag' => $tag]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## destroy

Visibilidad: `public`. Líneas originales 84–92.

```php
    public function destroy($id)
    {
        $tag = DocumentTag::find($id);
        if (!$tag) {
            return response()->json(['error' => 'Etiqueta no encontrada'], 404);
        }
        $tag->delete();
        return response()->json(['message' => 'Etiqueta eliminada']);
    }
```
