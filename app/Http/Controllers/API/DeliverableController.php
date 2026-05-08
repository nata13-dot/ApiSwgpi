<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Services\BusinessValidationService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

class DeliverableController extends Controller
{
    public function index(Request $request)
    {
        $query = Deliverable::with(['project', 'competencia', 'tags', 'submittedBy', 'calificadoPor']);
        
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
        $deliverable = Deliverable::with(['project', 'competencia', 'tags', 'submittedBy', 'calificadoPor'])->find($id);
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
                'project_id' => 'nullable|exists:projects,id',
                'competencia_id' => 'nullable|exists:competencias,id',
                'nombre' => 'nullable|string',
                'descripcion' => 'nullable|string',
                'estado' => 'nullable|in:pendiente,enviado,revisado,aprobado',
                'autores' => 'nullable|string',
                'tipo_documento' => 'nullable|in:reporte,video,presentacion,codigo,documento,otro',
                'rama_asociada' => 'nullable|string',
                'activo' => 'nullable|boolean',
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
        
        // Eliminar archivo si existe
        if ($deliverable->archivo_path) {
            FileService::deleteFile($deliverable->archivo_path);
        }
        
        $deliverable->delete();
        return response()->json(['message' => 'Entregable eliminado']);
    }

    /**
     * Endpoint para calificar un entregable
     * POST /deliverables/{id}/calificar
     * 
     * Solo docentes (asesores) y admin pueden calificar
     */
    public function calificar(Request $request, int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar que el usuario sea docente o admin
            $user = auth('api')->user();
            if (!in_array($user->perfil_id, [1, 2])) {
                return response()->json(['error' => 'Solo docentes y admin pueden calificar'], 403);
            }

            // Validar acceso: docente solo puede calificar sus proyectos
            if (!BusinessValidationService::validateAccesoEntrega($id, $user->id, $user->perfil_id)) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }

            $validated = $request->validate([
                'calificacion' => 'required|numeric|min:0|max:100',
            ]);

            // Validar que calificación esté en rango
            if (!BusinessValidationService::validateCalificacion($validated['calificacion'])) {
                return response()->json(['error' => 'La calificación debe estar entre 0 y 100'], 422);
            }

            // Actualizar entregable
            $deliverable->update([
                'calificacion' => $validated['calificacion'],
                'fecha_calificacion' => now(),
                'calificado_por' => $user->id,
                'estado' => 'aprobado',
            ]);

            return response()->json([
                'message' => 'Entregable calificado exitosamente',
                'deliverable' => $deliverable->load('calificadoPor')
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para descargar un archivo de entregable
     * GET /deliverables/{id}/download
     */
    public function download(int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar acceso
            $user = auth('api')->user();
            if (!BusinessValidationService::validateAccesoEntrega($id, $user->id, $user->perfil_id)) {
                return response()->json(['error' => 'No tienes acceso a este entregable'], 403);
            }

            // Descargar archivo
            return FileService::downloadDeliverable($deliverable);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint para subir archivo de entregable
     * POST /deliverables/{id}/upload
     */
    public function upload(Request $request, int $id)
    {
        try {
            $deliverable = Deliverable::find($id);
            if (!$deliverable) {
                return response()->json(['error' => 'Entregable no encontrado'], 404);
            }

            // Validar que el usuario sea el autor del entregable o admin
            $user = auth('api')->user();
            if ($user->id !== $deliverable->submitted_by && $user->perfil_id !== 1) {
                return response()->json(['error' => 'No puedes subir archivo a este entregable'], 403);
            }

            // Validar que haya archivo
            $request->validate([
                'archivo' => 'required|file|max:51200',
            ]);

            // Guardar archivo
            $result = FileService::storeDeliverableFile(
                $request->file('archivo'),
                $deliverable->id,
                $user->id
            );

            if (!$result['success']) {
                return response()->json(['error' => $result['error']], 422);
            }

            // Actualizar entregable
            if ($deliverable->archivo_path) {
                FileService::deleteFile($deliverable->archivo_path);
            }

            $deliverable->update([
                'archivo_path' => $result['path'],
                'estado' => 'enviado',
            ]);

            return response()->json([
                'message' => 'Archivo subido exitosamente',
                'deliverable' => $deliverable,
                'archivo_url' => FileService::getPublicUrl($result['path'])
            ], 200);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
