<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DocumentTag;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentTagController extends Controller
{
    public function index()
    {
        return response()->json(DocumentTag::where('activo', true)->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|unique:document_tags',
                'color' => 'nullable|string|size:7',
                'descripcion' => 'nullable|string',
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
                'nombre' => 'nullable|string|unique:document_tags,nombre,' . $tag->id,
                'color' => 'nullable|string|size:7',
                'descripcion' => 'nullable|string',
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
