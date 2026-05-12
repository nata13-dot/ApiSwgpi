<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationRoom;
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
        $query = Evaluation::with(['project.students', 'room.teachers', 'scores.teacher', 'attempts'])->orderByDesc('created_at');

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
                'evaluation_room_id' => 'nullable|exists:evaluation_rooms,id',
                'semestre' => 'required|integer|in:5,6,7,8',
                'sala' => 'nullable|string|max:50',
                'fecha_exposicion' => 'nullable|date',
                'estado' => 'nullable|in:programada,en_evaluacion,finalizada',
                'resultado' => 'nullable|in:pendiente,viable,no_viable',
            ]);

            $validated['etapa'] = $this->stageForSemester((int) $validated['semestre']);
            $validated['created_by'] = $user->id;

            if (!empty($validated['evaluation_room_id'])) {
                $room = EvaluationRoom::find($validated['evaluation_room_id']);
                $validated['sala'] = $room->nombre;
                $validated['fecha_exposicion'] = $validated['fecha_exposicion'] ?? $room->fecha_evaluacion;
            }

            $evaluation = Evaluation::updateOrCreate(
                ['project_id' => $validated['project_id'], 'evaluation_room_id' => $validated['evaluation_room_id'] ?? null],
                $validated
            )->load(['project.students', 'room.teachers', 'scores.teacher', 'attempts']);

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
        $evaluation = Evaluation::with(['project.students', 'room.teachers', 'scores.teacher', 'attempts'])->find($id);
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
                'evaluation_room_id' => 'nullable|exists:evaluation_rooms,id',
                'sala' => 'nullable|string|max:50',
                'fecha_exposicion' => 'nullable|date',
                'estado' => 'nullable|in:programada,en_evaluacion,finalizada',
                'resultado' => 'nullable|in:pendiente,viable,no_viable',
            ]);

            if (isset($validated['semestre'])) {
                $validated['etapa'] = $this->stageForSemester((int) $validated['semestre']);
            }

            $evaluation->update($validated);
            return response()->json(['message' => 'Evaluacion actualizada', 'evaluation' => $this->shapeEvaluation($evaluation->load(['project.students', 'room.teachers', 'scores.teacher', 'attempts']))]);
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
                'confirm_update' => 'nullable|boolean',
            ]);

            DB::transaction(function () use ($validated, $evaluation, $user) {
                $attempt = EvaluationAttempt::firstOrCreate(
                    ['evaluation_id' => $evaluation->id, 'teacher_id' => $user->id],
                    ['attempts_count' => 0]
                );
                $maxAttempts = $evaluation->room?->max_attempts ?? 1;
                $hasScores = EvaluationScore::where('evaluation_id', $evaluation->id)->where('teacher_id', $user->id)->exists();
                if ($hasScores && empty($validated['confirm_update'])) {
                    throw ValidationException::withMessages([
                        'confirm_update' => ["Ya evaluaste este proyecto. Si continuas, se modificara tu evaluacion actual. Oportunidades usadas: {$attempt->attempts_count}/{$maxAttempts}."],
                    ]);
                }
                if ($attempt->attempts_count >= $maxAttempts) {
                    throw ValidationException::withMessages([
                        'attempts' => ["Ya alcanzaste el limite de {$maxAttempts} oportunidad(es) para esta evaluacion."],
                    ]);
                }

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
                $attempt->update([
                    'attempts_count' => $attempt->attempts_count + 1,
                    'last_submitted_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Rubrica guardada',
                'evaluation' => $this->shapeEvaluation($evaluation->fresh(['project.students', 'room.teachers', 'scores.teacher', 'attempts'])),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function projects()
    {
        $query = Project::where('activo', true)->orderBy('title');
        if (request()->filled('semestre')) {
            $query->where('semestre', request('semestre'));
        }
        return response()->json($query->get(['id', 'title', 'semestre', 'authors']));
    }

    public function rooms(Request $request)
    {
        $query = EvaluationRoom::with(['teachers:id,nombres,apa,ama', 'projects:id,title,semestre'])
            ->where('activo', true)
            ->orderByDesc('fecha_evaluacion')
            ->orderBy('nombre');

        if ($request->filled('semestre')) {
            $query->where('semestre', $request->semestre);
        }

        return response()->json($query->get()->map(fn ($room) => $this->shapeRoom($room)));
    }

    public function storeRoom(Request $request)
    {
        $validated = $this->roomRules($request);
        $room = EvaluationRoom::create(collect($validated)->except(['teacher_ids', 'project_ids'])->toArray());
        $room->teachers()->sync($validated['teacher_ids'] ?? []);
        $room->projects()->sync($validated['project_ids'] ?? []);
        $this->syncRoomEvaluations($room);

        return response()->json(['message' => 'Sala creada', 'room' => $this->shapeRoom($room->load(['teachers', 'projects']))], 201);
    }

    public function updateRoom(Request $request, $id)
    {
        $room = EvaluationRoom::findOrFail($id);
        $validated = $this->roomRules($request);
        $room->update(collect($validated)->except(['teacher_ids', 'project_ids'])->toArray());
        $room->teachers()->sync($validated['teacher_ids'] ?? []);
        $room->projects()->sync($validated['project_ids'] ?? []);
        $this->syncRoomEvaluations($room);

        return response()->json(['message' => 'Sala actualizada', 'room' => $this->shapeRoom($room->load(['teachers', 'projects']))]);
    }

    public function destroyRoom($id)
    {
        EvaluationRoom::findOrFail($id)->update(['activo' => false]);
        return response()->json(['message' => 'Sala desactivada']);
    }

    public function studentSchedule()
    {
        $student = auth('api')->user();
        $evaluations = Evaluation::with(['project.students', 'room'])
            ->whereHas('project.students', fn ($query) => $query->where('users.id', $student->id))
            ->whereNotNull('evaluation_room_id')
            ->orderBy('fecha_exposicion')
            ->get()
            ->map(fn ($evaluation) => [
                'project_title' => $evaluation->project?->title,
                'room_name' => $evaluation->room?->nombre ?? $evaluation->sala,
                'classroom' => $evaluation->room?->salon,
                'presentation_minutes' => $evaluation->room?->project_presentation_minutes,
                'evaluation_minutes' => $evaluation->room?->teacher_evaluation_minutes,
                'date' => optional($evaluation->fecha_exposicion)->toDateTimeString(),
                'semester' => $evaluation->semestre,
            ]);

        return response()->json($evaluations);
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
            'evaluation_room_id' => $evaluation->evaluation_room_id,
            'project' => $evaluation->project,
            'semestre' => $evaluation->semestre,
            'etapa' => $evaluation->etapa,
            'sala' => $evaluation->sala,
            'room' => $evaluation->room ? $this->shapeRoom($evaluation->room) : null,
            'fecha_exposicion' => optional($evaluation->fecha_exposicion)->toDateTimeString(),
            'estado' => $evaluation->estado,
            'resultado' => $evaluation->resultado,
            'global_average' => $globalAverage,
            'evaluators_count' => $teacherBreakdown->count(),
            'teacher_breakdown' => $teacherBreakdown,
            'current_teacher_attempts' => optional($evaluation->attempts->firstWhere('teacher_id', auth('api')->id()))->attempts_count ?? 0,
            'current_teacher_has_scores' => $scores->where('teacher_id', auth('api')->id())->isNotEmpty(),
            'max_attempts' => $evaluation->room?->max_attempts ?? 1,
        ];
    }

    private function roomRules(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:80',
            'salon' => 'nullable|string|max:120',
            'semestre' => 'required|integer|in:5,6,7,8',
            'fecha_evaluacion' => 'required|date',
            'teacher_evaluation_minutes' => 'required|integer|min:1|max:240',
            'project_presentation_minutes' => 'required|integer|min:1|max:240',
            'max_attempts' => 'required|integer|min:1|max:10',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'string|exists:users,id',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:projects,id',
        ]);
    }

    private function syncRoomEvaluations(EvaluationRoom $room): void
    {
        $room->load('projects');
        foreach ($room->projects as $project) {
            Evaluation::updateOrCreate(
                ['project_id' => $project->id, 'evaluation_room_id' => $room->id],
                [
                    'semestre' => $room->semestre,
                    'etapa' => $this->stageForSemester($room->semestre),
                    'sala' => $room->nombre,
                    'fecha_exposicion' => $room->fecha_evaluacion,
                    'estado' => 'programada',
                    'resultado' => 'pendiente',
                    'created_by' => auth('api')->id(),
                ]
            );
        }
    }

    private function shapeRoom(EvaluationRoom $room): array
    {
        return [
            'id' => $room->id,
            'nombre' => $room->nombre,
            'salon' => $room->salon,
            'semestre' => $room->semestre,
            'fecha_evaluacion' => optional($room->fecha_evaluacion)->toDateTimeString(),
            'teacher_evaluation_minutes' => $room->teacher_evaluation_minutes,
            'project_presentation_minutes' => $room->project_presentation_minutes,
            'max_attempts' => $room->max_attempts,
            'teachers' => $room->teachers ?? collect(),
            'projects' => $room->projects ?? collect(),
        ];
    }
}
