<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SubjectGroup;
use App\Models\AcademicPeriod as AcademicPeriodModel;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Support\CareerContext;

class SubjectGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = SubjectGroup::with(['asignaturas', 'academicPeriod'])
            ->where('activo', true)
            ->whereBetween('semestre', [5, 9])
            ->orderBy('semestre')
            ->orderBy('grupo')
            ->orderBy('nombre');

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
            $group->asignaturas()->sync($this->courseSyncPayload($validated['asignatura_ids'] ?? []));

            return response()->json([
                'message' => 'Carga de asignaturas creada',
                'group' => $group->load(['asignaturas', 'academicPeriod']),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $group = SubjectGroup::with(['asignaturas', 'academicPeriod'])->find($id);
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
            $groupData = $this->groupData($validated);
            $this->ensureUniqueGroup($groupData['semestre'], $groupData['grupo'], (int) $group->id);

            $linked = DB::table('matriculas_academicas')->where('grupo_id', $group->id)->exists() || $group->projects()->exists()
                || DB::table('curso_estudiantes')->whereIn('curso_id', DB::table('cursos')->where('grupo_id', $group->id)->select('id'))->exists();
            if ($linked) abort_if((int) $group->semestre !== (int) $groupData['semestre'] || $group->grupo !== $groupData['grupo'] || (int) $group->periodo_id !== (int) $groupData['periodo_id'], 409, 'Un grupo con historial conserva su semestre, clave y periodo. Crea el grupo destino.');
            DB::transaction(function () use ($group, $groupData, $validated) {
                $group->update($groupData);
                $subjects = $validated['asignatura_ids'] ?? [];
                DB::table('cursos')->where('grupo_id', $group->id)->whereNotIn('asignatura_id', $subjects)->update(['activo' => false]);
                $payload = array_map(fn ($row) => $row + ['activo' => true], $this->courseSyncPayload($subjects));
                $group->asignaturas()->syncWithoutDetaching($payload);
            });

            return response()->json([
                'message' => 'Carga de asignaturas actualizada',
                'group' => $group->load(['asignaturas', 'academicPeriod']),
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

        abort_if(DB::table('matriculas_academicas')->where('grupo_id', $group->id)->exists(), 409, 'El grupo tiene matrículas; conserva su historial.');

        $group->update(['activo' => false]);
        return response()->json(['message' => 'Carga de asignaturas eliminada']);
    }

    public function students($id)
    {
        $group = SubjectGroup::find($id);
        if (!$group || !$group->activo) {
            return response()->json(['error' => 'Grupo no encontrado'], 404);
        }

        $students = User::students()
            ->where('activo', true)
            ->whereIn('usuarios.id', DB::table('matriculas_academicas')->where('carrera_id', $group->carrera_id)->where('periodo_id', $group->periodo_id)->where('grupo_id', $group->id)->where('estado', 'activa')->select('estudiante_id'))
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
            'student_ids.*' => ['string', Rule::exists('usuarios', 'id')->where('activo', true)],
        ]);

        $studentIds = array_values(array_unique($validated['student_ids'] ?? []));
        DB::transaction(function () use ($studentIds, $group) {
            foreach ($studentIds as $studentId) app(\App\Services\AcademicWorkflowService::class)->assign($studentId, $group);
        });

        return response()->json([
            'message' => 'Alumnos actualizados en el grupo',
            'updated' => count($studentIds),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'semestre' => 'required|integer|in:5,6,7,8,9',
            'grupo' => 'required|string|max:20',
            'periodo' => 'required|string|exists:periodos_academicos,nombre',
            'asignatura_ids' => 'nullable|array',
            'asignatura_ids.*' => [
                'integer',
                Rule::exists('asignaturas', 'id')
                    ->where('carrera_id', app(CareerContext::class)->careerId()),
            ],
        ]);

        $validated['nombre'] = trim($validated['nombre']);
        $validated['grupo'] = strtoupper(trim($validated['grupo']));
        $validated['periodo'] = trim($validated['periodo']);

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
        $period = AcademicPeriodModel::where('nombre', $validated['periodo'])->firstOrFail();

        abort_if(DB::table('carrera_periodos')->where('carrera_id', app(CareerContext::class)->careerId())->where('periodo_id', $period->id)->whereIn('estado', ['en_cierre', 'cerrado'])->exists(), 409, 'El periodo no admite cambios de grupos.');
        return [
            'nombre' => $validated['nombre'],
            'semestre' => $validated['semestre'],
            'grupo' => $validated['grupo'],
            'periodo_id' => $period->id,
            'activo' => true,
        ];
    }

    private function ensureUniqueGroup(int $semestre, string $grupo, ?int $ignoreId = null): void
    {
        $query = SubjectGroup::where('activo', true)
            ->where('semestre', $semestre)
            ->where('grupo', $grupo)->whereIn('periodo_id', AcademicPeriodModel::where('nombre', request('periodo'))->select('id'));

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'grupo' => ["Ya existe un grupo {$semestre} {$grupo}. Usa otra letra o edita el grupo existente."],
            ]);
        }
    }

    private function courseSyncPayload(array $subjectIds): array
    {
        $careerId = app(CareerContext::class)->careerId();

        return collect($subjectIds)
            ->mapWithKeys(fn ($subjectId) => [(int) $subjectId => ['carrera_id' => $careerId]])
            ->all();
    }
}
