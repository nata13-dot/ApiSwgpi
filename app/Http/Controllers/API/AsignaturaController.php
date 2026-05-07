<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AsignaturaController extends Controller
{
    public function index()
    {
        return response()->json(Asignatura::where('activo', true)->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'numero_creditos' => 'nullable|integer',
                'codigo' => 'nullable|string|unique:asignaturas',
            ]);

            $asignatura = Asignatura::create($validated);
            return response()->json(['message' => 'Asignatura creada', 'asignatura' => $asignatura], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $asignatura = Asignatura::with('competencias')->find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Asignatura no encontrada'], 404);
        }
        return response()->json($asignatura);
    }

    public function update(Request $request, $id)
    {
        try {
            $asignatura = Asignatura::find($id);
            if (!$asignatura) {
                return response()->json(['error' => 'Asignatura no encontrada'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string|max:255',
                'descripcion' => 'nullable|string',
                'numero_creditos' => 'nullable|integer',
                'activo' => 'nullable|boolean',
            ]);

            $asignatura->update($validated);
            return response()->json(['message' => 'Asignatura actualizada', 'asignatura' => $asignatura]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Asignatura no encontrada'], 404);
        }
        $asignatura->delete();
        return response()->json(['message' => 'Asignatura eliminada']);
    }
}
