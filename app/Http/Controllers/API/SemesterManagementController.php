<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Project;
use App\Models\SemesterPresentationException;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Services\SemesterManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SemesterManagementController extends Controller
{
    public function __construct(private readonly SemesterManagementService $semesterService)
    {
    }

    public function summary()
    {
        $activePeriod = $this->semesterService->syncCurrentPeriod();
        $periods = AcademicPeriod::query()
            ->withCount('subjectGroups')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();

        $exceptions = SemesterPresentationException::query()
            ->with([
                'period:id,nombre',
                'project:id,titulo,grupo_id',
                'project.subjectGroup:id,semestre,grupo',
                'student:id,nombres,apellido_paterno,apellido_materno,semestre,grupo',
            ])
            ->where('tipo', 'presentacion_semestre')
            ->where('activa', true)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($exception) => $exception->periodo_id !== null)
            ->values();

        return response()->json([
            'active_period_id' => $activePeriod?->id,
            'periods' => $periods,
            'exceptions' => $exceptions,
            'stats' => [
                'students' => User::where('perfil_id', 3)->where('activo', true)->count(),
                'projects' => Project::where('activo', true)->count(),
                'groups' => SubjectGroup::where('activo', true)->whereBetween('semestre', [5, 9])->count(),
                'exceptions' => $exceptions->count(),
            ],
        ]);
    }

    public function storePeriod(Request $request)
    {
        $validated = $this->validatePeriod($request);
        $period = AcademicPeriod::create($validated + ['activo' => false]);

        return response()->json(['message' => 'Periodo creado', 'period' => $period], 201);
    }

    public function updatePeriod(Request $request, AcademicPeriod $period)
    {
        $validated = $this->validatePeriod($request, $period->id);
        $period->update($validated);

        return response()->json(['message' => 'Periodo actualizado', 'period' => $period->fresh()]);
    }

    public function activatePeriod(AcademicPeriod $period)
    {
        $period = $this->semesterService->activate($period, true);

        return response()->json(['message' => 'Periodo activado', 'period' => $period]);
    }

    public function promotionPreview(AcademicPeriod $period)
    {
        $destinationSemesters = $this->semesterService->semestersForPeriod($period);
        $students = User::query()
            ->where('perfil_id', 3)
            ->where('activo', true)
            ->whereBetween('semestre', [5, 9])
            ->get(['id', 'semestre', 'grupo']);

        return response()->json([
            'period' => $period,
            'destination_semesters' => $destinationSemesters,
            'movements' => $students
                ->map(fn ($student) => [
                    'from' => (int) $student->semestre,
                    'to' => (int) $student->semestre + 1,
                    'eligible' => in_array((int) $student->semestre + 1, $destinationSemesters, true),
                ])
                ->filter('eligible')
                ->groupBy(fn ($movement) => "{$movement['from']}-{$movement['to']}")
                ->map(fn ($items) => [
                    'from' => $items->first()['from'],
                    'to' => $items->first()['to'],
                    'students' => $items->count(),
                ])
                ->values(),
        ]);
    }

    public function applyPromotion(AcademicPeriod $period)
    {
        $summary = $this->semesterService->promoteStudents($period);
        $period->update(['promocion_aplicada_en' => now()]);

        return response()->json(['message' => 'Promocion aplicada', 'summary' => $summary]);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['students' => [], 'projects' => []]);
        }

        $students = User::query()
            ->where('perfil_id', 3)
            ->where('activo', true)
            ->where(function ($query) use ($term) {
                $query->where('id', 'like', "%{$term}%")
                    ->orWhere('nombres', 'like', "%{$term}%")
                    ->orWhere('apellido_paterno', 'like', "%{$term}%")
                    ->orWhere('apellido_materno', 'like', "%{$term}%");
            })
            ->limit(12)
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'semestre', 'grupo']);

        $projects = Project::query()
            ->with('subjectGroup:id,semestre,grupo')
            ->where('activo', true)
            ->where('titulo', 'like', "%{$term}%")
            ->limit(12)
            ->get(['id', 'titulo', 'grupo_id']);

        return response()->json(['students' => $students, 'projects' => $projects]);
    }

    public function storeException(Request $request)
    {
        $validated = $request->validate([
            'period_id' => ['required', 'integer', Rule::exists('periodos_academicos', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('proyectos', 'id')->where('activo', true)],
            'student_id' => ['nullable', 'string', Rule::exists('usuarios', 'id')->where('activo', true)->where('perfil_id', 3)],
            'presentation_semester' => 'required|integer|in:5,6,7,8,9',
            'reason' => 'nullable|string|max:500',
        ]);

        if (empty($validated['project_id']) === empty($validated['student_id'])) {
            throw ValidationException::withMessages([
                'target' => ['Selecciona un proyecto o un alumno, pero no ambos.'],
            ]);
        }

        $projectId = $validated['project_id'] ?? null;
        if (!$projectId && !empty($validated['student_id'])) {
            $projectId = DB::table('proyecto_integrantes')
                ->join('proyectos', 'proyectos.id', '=', 'proyecto_integrantes.proyecto_id')
                ->where('proyecto_integrantes.usuario_id', $validated['student_id'])
                ->whereIn('proyecto_integrantes.rol', ['lider', 'integrante'])
                ->where('proyectos.activo', true)
                ->orderByDesc('proyectos.creado_en')
                ->value('proyectos.id');

            if (!$projectId) {
                throw ValidationException::withMessages([
                    'student_id' => ['El alumno debe pertenecer a un proyecto activo para autorizar una presentación especial.'],
                ]);
            }
        }

        $keys = [
            'proyecto_id' => $projectId,
            'usuario_id' => $validated['student_id'] ?? null,
            'tipo' => 'presentacion_semestre',
        ];
        $exception = SemesterPresentationException::updateOrCreate($keys, [
            'valor' => SemesterPresentationException::encodedValue(
                (int) $validated['period_id'],
                (int) $validated['presentation_semester']
            ),
            'motivo' => trim((string) ($validated['reason'] ?? '')) ?: 'Presentacion autorizada en semestre excepcional',
            'autorizada_por' => auth('api')->id(),
            'activa' => true,
        ]);

        return response()->json([
            'message' => 'Excepcion de presentacion guardada',
            'exception' => $exception->load(['period', 'project.subjectGroup', 'student']),
        ]);
    }

    public function destroyException(SemesterPresentationException $exception)
    {
        if ($exception->tipo !== 'presentacion_semestre') {
            return response()->json(['error' => 'Excepcion no encontrada'], 404);
        }

        $exception->update(['activa' => false]);

        return response()->json(['message' => 'Excepcion eliminada']);
    }

    private function validatePeriod(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('periodos_academicos', 'nombre')->ignore($ignoreId),
            ],
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'automatic_promotion' => 'required|boolean',
        ]);

        $overlap = AcademicPeriod::query()
            ->when($ignoreId, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->whereDate('fecha_inicio', '<=', $validated['ends_at'])
            ->whereDate('fecha_fin', '>=', $validated['starts_at'])
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['starts_at' => ['Las fechas se cruzan con otro periodo.']]);
        }

        return [
            'nombre' => trim($validated['name']),
            'fecha_inicio' => $validated['starts_at'],
            'fecha_fin' => $validated['ends_at'],
            'promocion_automatica' => $validated['automatic_promotion'],
        ];
    }
}
