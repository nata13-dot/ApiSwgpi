<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubjectGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = SubjectGroup::with('asignaturas')->where('activo', true)->orderBy('semestre')->orderBy('grupo')->orderBy('nombre');

        if ($request->filled('semestre')) {
            $query->where('semestre', $request->semestre);
        }

        if ($request->filled('grupo')) {
            $query->where('grupo', strtoupper($request->grupo));
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validatePayload($request);
            $group = SubjectGroup::create($this->groupData($validated));
            $group->asignaturas()->sync($validated['asignatura_ids'] ?? []);

            return response()->json([
                'message' => 'Carga de asignaturas creada',
                'group' => $group->load('asignaturas'),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $group = SubjectGroup::with('asignaturas')->find($id);
        if (!$group || !$group->activo) {
            return response()->json(['error' => 'Carga de asignaturas no encontrada'], 404);
        }

        return response()->json($group);
    }

    public function update(Request $request, $id)
    {
        try {
            $group = SubjectGroup::find($id);
            if (!$group || !$group->activo) {
                return response()->json(['error' => 'Carga de asignaturas no encontrada'], 404);
            }

            $validated = $this->validatePayload($request);
            $group->update($this->groupData($validated));
            $group->asignaturas()->sync($validated['asignatura_ids'] ?? []);

            foreach ($group->projects as $project) {
                $project->asignaturas()->sync($validated['asignatura_ids'] ?? []);
            }

            return response()->json([
                'message' => 'Carga de asignaturas actualizada',
                'group' => $group->load('asignaturas'),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $group = SubjectGroup::find($id);
        if (!$group || !$group->activo) {
            return response()->json(['error' => 'Carga de asignaturas no encontrada'], 404);
        }

        if ($group->projects()->exists()) {
            return response()->json(['message' => 'No puedes eliminar una carga usada por proyectos.'], 422);
        }

        $group->update(['activo' => false]);
        return response()->json(['message' => 'Carga de asignaturas eliminada']);
    }

    public function students($id)
    {
        $group = SubjectGroup::find($id);
        if (!$group || !$group->activo) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }

        $students = User::where('perfil_id', 3)
            ->where('activo', true)
            ->where('semestre', $group->semestre)
            ->where('grupo', $group->grupo)
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apa', 'ama', 'email', 'semestre', 'grupo']);

        return response()->json(['group' => $group, 'students' => $students]);
    }

    public function syncStudents(Request $request, $id)
    {
        $group = SubjectGroup::find($id);
        if (!$group || !$group->activo) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }

        $validated = $request->validate([
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'string|exists:users,id',
        ]);

        $studentIds = array_values(array_unique($validated['student_ids'] ?? []));
        User::whereIn('id', $studentIds)
            ->where('perfil_id', 3)
            ->update(['semestre' => $group->semestre, 'grupo' => $group->grupo]);

        return response()->json([
            'message' => 'Alumnos actualizados en el grupo',
            'updated' => count($studentIds),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'semestre' => 'required|integer|in:5,6,7,8',
            'grupo' => 'required|string|max:20',
            'periodo' => 'nullable|string|max:100',
            'asignatura_ids' => 'nullable|array',
            'asignatura_ids.*' => 'integer|exists:asignaturas,id',
        ]);
    }

    private function groupData(array $validated): array
    {
        return [
            'nombre' => $validated['nombre'],
            'semestre' => $validated['semestre'],
            'grupo' => strtoupper(trim($validated['grupo'])),
            'periodo' => $validated['periodo'] ?? null,
            'activo' => true,
        ];
    }
}
