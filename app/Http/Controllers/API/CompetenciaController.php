<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Competencia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetenciaController extends Controller
{
    public function index()
    {
        return response()->json(Competencia::where('activo', true)->with('asignatura')->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'asignatura_id' => 'nullable|exists:asignaturas,id',
            ]);

            $competencia = Competencia::create($validated);
            return response()->json(['message' => 'Competencia creada', 'competencia' => $competencia], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $competencia = Competencia::with(['asignatura', 'deliverables'])->find($id);
        if (!$competencia) {
            return response()->json(['error' => 'Competencia no encontrada'], 404);
        }
        return response()->json($competencia);
    }

    public function update(Request $request, $id)
    {
        try {
            $competencia = Competencia::find($id);
            if (!$competencia) {
                return response()->json(['error' => 'Competencia no encontrada'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'asignatura_id' => 'nullable|exists:asignaturas,id',
                'activo' => 'nullable|boolean',
            ]);

            $competencia->update($validated);
            return response()->json(['message' => 'Competencia actualizada', 'competencia' => $competencia]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $competencia = Competencia::find($id);
        if (!$competencia) {
            return response()->json(['error' => 'Competencia no encontrada'], 404);
        }
        $competencia->delete();
        return response()->json(['message' => 'Competencia eliminada']);
    }
}
