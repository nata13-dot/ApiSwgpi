<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Project;
use App\Models\RubricCriterion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    private array $levels = [
        'nada' => 0,
        'poco' => 1,
        'bastante' => 2,
        'mucho' => 3,
    ];

    public function criteria(Request $request)
    {
        $semester = $request->integer('semestre');
        $query = RubricCriterion::query()->where('activo', true)->orderBy('semestre')->orderBy('orden')->orderBy('id');

        if ($semester) {
            $query->where('semestre', $semester);
        }

        return response()->json([
            'criteria' => $query->get()->map(fn ($criterion) => $this->shapeCriterion($criterion)),
            'levels' => array_keys($this->levels),
        ]);
    }

    public function storeCriterion(Request $request)
    {
        try {
            $validated = $request->validate([
                'semestre' => 'required|integer|in:5,6,7,8',
                'pregunta' => 'required|string|max:255',
                'orden' => 'nullable|integer|min:0',
            ]);

            $baseKey = Str::slug($validated['pregunta'], '_') ?: 'criterio';
            $key = $baseKey;
            $suffix = 2;
            while (RubricCriterion::where('semestre', $validated['semestre'])->where('clave', $key)->exists()) {
                $key = $baseKey . '_' . $suffix;
                $suffix++;
            }

            $criterion = RubricCriterion::create([
                'semestre' => $validated['semestre'],
                'clave' => $key,
                'pregunta' => $validated['pregunta'],
                'orden' => $validated['orden'] ?? 0,
                'activo' => true,
            ]);

            return response()->json(['message' => 'Pregunta creada', 'criterion' => $this->shapeCriterion($criterion)], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function updateCriterion(Request $request, $id)
    {
        $criterion = RubricCriterion::find($id);
        if (!$criterion) {
            return response()->json(['error' => 'Pregunta no encontrada'], 404);
        }

        try {
            $validated = $request->validate([
                'pregunta' => 'required|string|max:255',
                'orden' => 'nullable|integer|min:0',
                'activo' => 'nullable|boolean',
            ]);

            $criterion->update($validated);
            return response()->json(['message' => 'Pregunta actualizada', 'criterion' => $this->shapeCriterion($criterion)]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroyCriterion($id)
    {
        $criterion = RubricCriterion::find($id);
        if (!$criterion) {
            return response()->json(['error' => 'Pregunta no encontrada'], 404);
        }

        $criterion->update(['activo' => false]);
        return response()->json(['message' => 'Pregunta desactivada']);
    }

    public function index(Request $request)
    {
        $query = Evaluation::with(['project', 'scores.teacher'])->orderByDesc('created_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $evaluations = $query->paginate(15);
        $evaluations->getCollection()->transform(fn ($evaluation) => $this->shapeEvaluation($evaluation));

        return response()->json($evaluations);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();
        if (!in_array($user->perfil_id, [1, 2])) {
            return response()->json(['error' => 'Solo administradores y docentes pueden crear evaluaciones'], 403);
        }

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'semestre' => 'required|integer|in:5,6,7,8',
                'sala' => 'nullable|string|max:50',
                'fecha_exposicion' => 'nullable|date',
                'estado' => 'nullable|in:programada,en_evaluacion,finalizada',
                'resultado' => 'nullable|in:pendiente,viable,no_viable',
            ]);

            $validated['etapa'] = $this->stageForSemester((int) $validated['semestre']);
            $validated['created_by'] = $user->id;

            $evaluation = Evaluation::create($validated)->load(['project', 'scores.teacher']);

            return response()->json([
                'message' => 'Evaluacion creada',
                'evaluation' => $this->shapeEvaluation($evaluation),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function show($id)
    {
        $evaluation = Evaluation::with(['project', 'scores.teacher'])->find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        return response()->json($this->shapeEvaluation($evaluation));
    }

    public function update(Request $request, $id)
    {
        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        try {
            $validated = $request->validate([
                'semestre' => 'nullable|integer|in:5,6,7,8',
                'sala' => 'nullable|string|max:50',
                'fecha_exposicion' => 'nullable|date',
                'estado' => 'nullable|in:programada,en_evaluacion,finalizada',
                'resultado' => 'nullable|in:pendiente,viable,no_viable',
            ]);

            if (isset($validated['semestre'])) {
                $validated['etapa'] = $this->stageForSemester((int) $validated['semestre']);
            }

            $evaluation->update($validated);
            return response()->json(['message' => 'Evaluacion actualizada', 'evaluation' => $this->shapeEvaluation($evaluation->load(['project', 'scores.teacher']))]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        $evaluation->delete();
        return response()->json(['message' => 'Evaluacion eliminada']);
    }

    public function score(Request $request, $id)
    {
        $user = auth('api')->user();
        if (!in_array($user->perfil_id, [1, 2])) {
            return response()->json(['error' => 'Solo administradores y docentes pueden evaluar'], 403);
        }

        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        $validCriteria = RubricCriterion::where('semestre', $evaluation->semestre)
            ->where('activo', true)
            ->pluck('clave')
            ->all();

        try {
            $validated = $request->validate([
                'scores' => 'required|array',
                'scores.*.criterio' => ['required', 'string', Rule::in($validCriteria)],
                'scores.*.nivel' => 'required|string|in:nada,poco,bastante,mucho',
                'scores.*.comentario' => 'nullable|string',
            ]);

            DB::transaction(function () use ($validated, $evaluation, $user) {
                foreach ($validated['scores'] as $score) {
                    EvaluationScore::updateOrCreate(
                        [
                            'evaluation_id' => $evaluation->id,
                            'teacher_id' => $user->id,
                            'criterio' => $score['criterio'],
                        ],
                        [
                            'nivel' => $score['nivel'],
                            'puntaje' => $this->levels[$score['nivel']],
                            'comentario' => $score['comentario'] ?? null,
                        ]
                    );
                }
            });

            return response()->json([
                'message' => 'Rubrica guardada',
                'evaluation' => $this->shapeEvaluation($evaluation->fresh(['project', 'scores.teacher'])),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function projects()
    {
        return response()->json(Project::where('activo', true)->orderBy('title')->get(['id', 'title']));
    }

    private function stageForSemester(int $semester): string
    {
        return match ($semester) {
            5 => 'propuesta',
            8 => 'titulacion',
            default => 'avance',
        };
    }

    private function shapeCriterion(RubricCriterion $criterion): array
    {
        return [
            'id' => $criterion->id,
            'semestre' => $criterion->semestre,
            'key' => $criterion->clave,
            'label' => $criterion->pregunta,
            'orden' => $criterion->orden,
        ];
    }

    private function criteriaLabelsForSemester(int $semester): array
    {
        return RubricCriterion::where('semestre', $semester)
            ->pluck('pregunta', 'clave')
            ->all();
    }

    private function shapeEvaluation(Evaluation $evaluation): array
    {
        $scores = $evaluation->scores;
        $labels = $this->criteriaLabelsForSemester($evaluation->semestre);
        $globalAverage = $scores->count() === 0 ? 0 : round(($scores->avg('puntaje') / 3) * 100, 2);

        $teacherBreakdown = $scores
            ->groupBy('teacher_id')
            ->map(function ($teacherScores) use ($labels) {
                $teacher = $teacherScores->first()->teacher;
                return [
                    'teacher_id' => $teacher?->id,
                    'teacher_name' => trim(($teacher?->nombres ?? '') . ' ' . ($teacher?->apa ?? '') . ' ' . ($teacher?->ama ?? '')) ?: 'Docente',
                    'average' => round(($teacherScores->avg('puntaje') / 3) * 100, 2),
                    'scores' => $teacherScores->map(fn ($score) => [
                        'criterio' => $score->criterio,
                        'criterio_label' => $labels[$score->criterio] ?? $score->criterio,
                        'nivel' => $score->nivel,
                        'puntaje' => $score->puntaje,
                        'comentario' => $score->comentario,
                    ])->values(),
                ];
            })
            ->values();

        return [
            'id' => $evaluation->id,
            'project_id' => $evaluation->project_id,
            'project' => $evaluation->project,
            'semestre' => $evaluation->semestre,
            'etapa' => $evaluation->etapa,
            'sala' => $evaluation->sala,
            'fecha_exposicion' => optional($evaluation->fecha_exposicion)->toDateTimeString(),
            'estado' => $evaluation->estado,
            'resultado' => $evaluation->resultado,
            'global_average' => $globalAverage,
            'evaluators_count' => $teacherBreakdown->count(),
            'teacher_breakdown' => $teacherBreakdown,
        ];
    }
}