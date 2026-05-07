<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with(['creator', 'advisors', 'asignaturas'])
                           ->where('activo', true)
                           ->paginate(15);
        return response()->json($projects);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            $validated['created_by'] = auth('api')->id();
            $project = Project::create($validated);

            return response()->json(['message' => 'Proyecto creado', 'project' => $project], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $project = Project::with(['creator', 'advisors', 'asignaturas', 'deliverables'])->find($id);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        try {
            $project = Project::find($id);
            if (!$project) {
                return response()->json(['error' => 'Proyecto no encontrado'], 404);
            }

            $validated = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'activo' => 'nullable|boolean',
            ]);

            $project->update($validated);
            return response()->json(['message' => 'Proyecto actualizado', 'project' => $project]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $project = Project::find($id);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }
        $project->delete();
        return response()->json(['message' => 'Proyecto eliminado']);
    }

    public function addAdvisor(Request $request, $id)
    {
        try {
            $project = Project::find($id);
            if (!$project) {
                return response()->json(['error' => 'Proyecto no encontrado'], 404);
            }

            $validated = $request->validate([
                'user_id' => 'required|string|exists:users,id',
                'rol_asesor' => 'required|in:primario,secundario',
            ]);

            $project->advisors()->attach($validated['user_id'], ['rol_asesor' => $validated['rol_asesor']]);
            return response()->json(['message' => 'Asesor añadido']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function removeAdvisor($projectId, $userId)
    {
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Proyecto no encontrado'], 404);
        }
        
        $project->advisors()->detach($userId);
        return response()->json(['message' => 'Asesor removido']);
    }
}
