<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            $groupData = $this->groupData($validated);
            $this->ensureUniqueGroup($groupData['semestre'], $groupData['grupo']);

            $group = SubjectGroup::create($groupData);
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

            $previousSemester = $group->semestre;
            $previousGroup = $group->grupo;
            $validated = $this->validatePayload($request);
            $groupData = $this->groupData($validated);
            $this->ensureUniqueGroup($groupData['semestre'], $groupData['grupo'], (int) $group->id);

            $group->update($groupData);
            $group->asignaturas()->sync($validated['asignatura_ids'] ?? []);

            User::where('perfil_id', 3)
                ->where('activo', true)
                ->where('semestre', $previousSemester)
                ->where('grupo', $previousGroup)
                ->update([
                    'semestre' => $group->semestre,
                    'grupo' => $group->grupo,
                ]);

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

        User::where('perfil_id', 3)
            ->where('activo', true)
            ->where('semestre', $group->semestre)
            ->where('grupo', $group->grupo)
            ->update([
                'semestre' => null,
                'grupo' => null,
            ]);

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
            'student_ids.*' => ['string', Rule::exists('users', 'id')->where('activo', true)->where('perfil_id', 3)],
        ]);

        $studentIds = array_values(array_unique($validated['student_ids'] ?? []));
        User::whereIn('id', $studentIds)
            ->where('perfil_id', 3)
            ->where('activo', true)
            ->update(['semestre' => $group->semestre, 'grupo' => $group->grupo]);

        return response()->json([
            'message' => 'Alumnos actualizados en el grupo',
            'updated' => count($studentIds),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'semestre' => 'required|integer|in:5,6,7,8',
            'grupo' => 'required|string|max:20',
            'periodo' => 'nullable|string|max:100',
            'asignatura_ids' => 'nullable|array',
            'asignatura_ids.*' => 'integer|exists:asignaturas,id',
        ]);

        $validated['nombre'] = trim($validated['nombre']);
        $validated['grupo'] = strtoupper(trim($validated['grupo']));
        $validated['periodo'] = isset($validated['periodo']) ? trim((string) $validated['periodo']) : null;

        if ($validated['nombre'] === '') {
            throw ValidationException::withMessages(['nombre' => ['El nombre del grupo es obligatorio.']]);
        }

        if ($validated['grupo'] === '') {
            throw ValidationException::withMessages(['grupo' => ['La letra o clave del grupo es obligatoria.']]);
        }

        return $validated;
    }

    private function groupData(array $validated): array
    {
        return [
            'nombre' => $validated['nombre'],
            'semestre' => $validated['semestre'],
            'grupo' => $validated['grupo'],
            'periodo' => $validated['periodo'] ?: null,
            'activo' => true,
        ];
    }

    private function ensureUniqueGroup(int $semestre, string $grupo, ?int $ignoreId = null): void
    {
        $query = SubjectGroup::where('activo', true)
            ->where('semestre', $semestre)
            ->where('grupo', $grupo);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'grupo' => ["Ya existe un grupo {$semestre} {$grupo}. Usa otra letra o edita el grupo existente."],
            ]);
        }
    }
}
