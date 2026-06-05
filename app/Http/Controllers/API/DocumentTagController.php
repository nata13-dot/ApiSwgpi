<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTag;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentTagController extends Controller
{
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

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:100|unique:etiquetas_documentos,nombre',
                'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'descripcion' => 'nullable|string|max:1000',
            ]);

            $tag = DocumentTag::create($validated);
            return response()->json(['message' => 'Etiqueta creada', 'tag' => $tag], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $tag = DocumentTag::with('deliverables')->find($id);
        if (!$tag) {
            return response()->json(['error' => 'Etiqueta no encontrada'], 404);
        }
        return response()->json($tag);
    }

    public function update(Request $request, $id)
    {
        try {
            $tag = DocumentTag::find($id);
            if (!$tag) {
                return response()->json(['error' => 'Etiqueta no encontrada'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:100|unique:etiquetas_documentos,nombre,' . $tag->id,
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

    public function destroy($id)
    {
        $tag = DocumentTag::find($id);
        if (!$tag) {
            return response()->json(['error' => 'Etiqueta no encontrada'], 404);
        }
        $tag->delete();
        return response()->json(['message' => 'Etiqueta eliminada']);
    }
}
