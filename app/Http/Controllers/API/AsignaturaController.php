<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignaturaController extends Controller
{
    public function index()
    {
        return response()->json(Asignatura::orderBy('nombre')->paginate(15));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'clave' => 'nullable|string|max:50|unique:asignaturas,clave',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
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
                'clave' => ['nullable', 'string', 'max:50', Rule::unique('asignaturas', 'clave')->ignore($asignatura->id)],
                'nombre' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
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