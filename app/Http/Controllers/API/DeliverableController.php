<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliverableController extends Controller
{
    public function index(Request $request)
    {
        $query = Deliverable::with(['project', 'tags', 'submittedBy']);
        
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query->where(function($q) use ($term) {
                $q->where('nombre', 'like', "%{$term}%")
                  ->orWhere('descripcion', 'like', "%{$term}%");
            });
        }

        return response()->json($query->paginate(12));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'nombre' => 'required|string',
                'descripcion' => 'nullable|string',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string',
                'competencia_id' => 'nullable|exists:competencias,id',
                'autores' => 'nullable|string',
            ]);

            $validated['submitted_by'] = auth('api')->id();
            $validated['estado'] = 'pendiente';
            $deliverable = Deliverable::create($validated);

            return response()->json(['message' => 'Entregable creado', 'deliverable' => $deliverable], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $deliverable = Deliverable::with(['project', 'tags', 'submittedBy'])->find($id);
        if (!$deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }
        return response()->json($deliverable);
    }

    public function update(Request $request, $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            $validated = $request->validate([
                'nombre' => 'nullable|string',
                'descripcion' => 'nullable|string',
                'estado' => 'nullable|in:pendiente,enviado,revisado,aprobado',
                'autores' => 'nullable|string',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string',
            ]);

            $deliverable->update($validated);
            return response()->json(['message' => 'Entregable actualizado', 'deliverable' => $deliverable]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $deliverable = Deliverable::find($id);
        if (!$deliverable) {
            return response()->json(['error' => 'Entregable no encontrado'], 404);
        }
        $deliverable->delete();
        return response()->json(['message' => 'Entregable eliminado']);
    }
}
