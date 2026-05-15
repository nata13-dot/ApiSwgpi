<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Competencia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetenciaController extends Controller
{
    public function index(Request $request)
    {
        $query = Competencia::with('asignatura')->withCount('deliverables');

        if ($request->filled('asignatura_id')) {
            $query->where('asignatura_id', $request->asignatura_id);
        }

        return response()->json($query->orderBy('nombre')->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'asignatura_id' => 'nullable|exists:asignaturas,id',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
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
                'nombre' => 'required|string|max:255',
                'asignatura_id' => 'nullable|exists:asignaturas,id',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
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
