<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Asignatura;
use App\Models\Competencia;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CompetenciaController extends Controller
{
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

    public function show($id)
    {
        $asignatura = Asignatura::find($id);
        if (!$asignatura) {
            return response()->json(['error' => 'Competencia no encontrada'], 404);
        }
        return response()->json($this->fromAsignatura($asignatura));
    }

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
}
