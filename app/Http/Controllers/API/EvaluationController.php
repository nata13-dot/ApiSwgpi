<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Evaluation;
use App\Models\EvaluationAttempt;
use App\Models\EvaluationRoom;
use App\Models\EvaluationScore;
use App\Models\Project;
use App\Models\RubricCriterion;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    private array $criteriaLabelCache = [];

    private array $levels = [
        'totalmente_de_acuerdo' => 4,
        'de_acuerdo' => 3,
        'neutral' => 2,
        'en_desacuerdo' => 1,
        'totalmente_en_desacuerdo' => 0,
    ];

    private array $legacyLevels = [
        'nada' => 0,
        'poco' => 1,
        'bastante' => 2,
        'mucho' => 3,
    ];

    private array $levelLabels = [
        'totalmente_de_acuerdo' => 'Totalmente de acuerdo',
        'de_acuerdo' => 'De acuerdo',
        'neutral' => 'Neutral',
        'en_desacuerdo' => 'En desacuerdo',
        'totalmente_en_desacuerdo' => 'Totalmente en desacuerdo',
        'nada' => 'Nada',
        'poco' => 'Poco',
        'bastante' => 'Bastante',
        'mucho' => 'Mucho',
    ];

    public function criteria(Request $request)
    {
        $semester = $request->integer('semestre');
        $query = RubricCriterion::query()->where('activo', true)->orderBy('semestre')->orderBy('orden')->orderBy('id');

        if ($semester) {
            $query->where('semestre', $semester);
        }
        if ($request->filled('project_id')) {
            $projectId = (int) $request->project_id;
            $query->where(function ($scope) use ($projectId) {
                $scope->whereNull('project_id')->orWhere('project_id', $projectId);
            });
        }

        return response()->json([
            'criteria' => $query->get()->map(fn ($criterion) => $this->shapeCriterion($criterion)),
            'levels' => collect($this->levels)->map(fn ($score, $key) => [
                'key' => $key,
                'label' => $this->levelLabels[$key],
                'puntaje' => $score,
            ])->values(),
            'score_modes' => $this->rubricScoreModes(),
        ]);
    }

    public function updateRubricScoreModes(Request $request)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $validated = $request->validate([
            'semester' => 'required|integer|in:5,6,7,8',
            'mode' => ['required', Rule::in(['levels', 'numeric'])],
        ]);

        $modes = $this->rubricScoreModes();
        $modes[(string) $validated['semester']] = $validated['mode'];
        SystemSetting::setValue('rubric_score_modes', $modes, 'array', 'Metodo de puntaje de rubrica por semestre');

        return response()->json(['message' => 'Metodo de rubrica guardado', 'score_modes' => $modes]);
    }

    public function storeCriterion(Request $request)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        try {
            $validated = $request->validate([
                'semestre' => 'required|integer|in:5,6,7,8',
                'project_id' => 'nullable|integer|exists:projects,id',
                'pregunta' => 'required|string|max:255',
                'orden' => 'nullable|integer|min:0',
            ]);

            $project = null;
            if (!empty($validated['project_id'])) {
                $project = Project::where('id', $validated['project_id'])
                    ->where('semestre', 8)
                    ->where('activo', true)
                    ->first();
                if (!$project || (int) $validated['semestre'] !== 8) {
                    throw ValidationException::withMessages([
                        'project_id' => ['La rubrica personalizada solo aplica a proyectos activos de 8vo semestre.'],
                    ]);
                }
            }

            $baseKey = Str::limit(Str::slug($validated['pregunta'], '_') ?: 'criterio', 64, '');
            $key = $project ? Str::limit('p' . $project->id . '_' . $baseKey, 80, '') : $baseKey;
            $suffix = 2;
            while (RubricCriterion::where('semestre', $validated['semestre'])->where('clave', $key)->exists()) {
                $prefix = $project ? 'p' . $project->id . '_' : '';
                $key = $prefix . Str::limit($baseKey, 80 - strlen($prefix) - strlen((string) $suffix) - 1, '') . '_' . $suffix;
                $suffix++;
            }

            $criterion = RubricCriterion::create([
                'semestre' => $validated['semestre'],
                'project_id' => $validated['project_id'] ?? null,
                'clave' => $key,
                'pregunta' => $validated['pregunta'],
                'orden' => $validated['orden'] ?? ((int) RubricCriterion::where('semestre', $validated['semestre'])->where('project_id', $validated['project_id'] ?? null)->max('orden') + 1),
                'activo' => true,
            ]);

            return response()->json(['message' => 'Pregunta creada', 'criterion' => $this->shapeCriterion($criterion)], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function updateCriterion(Request $request, $id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

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

            DB::transaction(function () use ($criterion, $validated) {
                $criterion->update($validated);

                if (array_key_exists('activo', $validated) && !$validated['activo']) {
                    EvaluationScore::where('criterio', $criterion->clave)
                        ->whereHas('evaluation', fn ($query) => $query->where('semestre', $criterion->semestre))
                        ->delete();
                }
            });

            return response()->json(['message' => 'Pregunta actualizada', 'criterion' => $this->shapeCriterion($criterion)]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroyCriterion($id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $criterion = RubricCriterion::find($id);
        if (!$criterion) {
            return response()->json(['error' => 'Pregunta no encontrada'], 404);
        }

        DB::transaction(function () use ($criterion) {
            $criterion->update(['activo' => false]);
            EvaluationScore::where('criterio', $criterion->clave)
                ->whereHas('evaluation', fn ($query) => $query->where('semestre', $criterion->semestre))
                ->delete();
        });

        return response()->json(['message' => 'Pregunta desactivada']);
    }

    public function index(Request $request)
    {
        $user = auth('api')->user();
        $archived = $request->boolean('archived');
        $supportsArchive = Schema::hasColumn('evaluations', 'archived_at');

        if ($archived && !$supportsArchive) {
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ]);
        }

        $query = Evaluation::with(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher'])
            ->when($supportsArchive && $archived, fn ($scope) => $scope->whereNotNull('archived_at'))
            ->when($supportsArchive && !$archived, fn ($scope) => $scope->whereNull('archived_at'))
            ->orderBy('evaluation_room_id')
            ->orderBy('presentation_order')
            ->orderByDesc('created_at');

        if ((int) $user->perfil_id === 2 && !$this->isEvaluationManager($user)) {
            $query->where(function ($scope) use ($user) {
                $scope->whereHas('room.teachers', fn ($teacherQuery) => $teacherQuery->where('users.id', $user->id))
                    ->orWhereHas('room', fn ($roomQuery) => $roomQuery->where('responsible_teacher_id', $user->id));
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $evaluations = $query->paginate(15);
        $evaluations->getCollection()->transform(fn ($evaluation) => $this->shapeEvaluation($evaluation));

        return response()->json($evaluations);
    }

    public function archive($id)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        if (!Schema::hasColumn('evaluations', 'archived_at')) {
            return response()->json(['message' => 'La migracion de archivo de evaluaciones aun no esta aplicada.'], 409);
        }

        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        $evaluation->update([
            'archived_at' => now(),
            'archived_by' => $user->id,
        ]);

        return response()->json(['message' => 'Evaluacion archivada']);
    }

    public function unarchive($id)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        if (!Schema::hasColumn('evaluations', 'archived_at')) {
            return response()->json(['message' => 'La migracion de archivo de evaluaciones aun no esta aplicada.'], 409);
        }

        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        $evaluation->update([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        return response()->json(['message' => 'Evaluacion restaurada']);
    }

    public function store(Request $request)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'evaluation_room_id' => 'nullable|exists:evaluation_rooms,id',
                'semestre' => 'required|integer|in:5,6,7,8',
                'sala' => 'nullable|string|max:50',
                'fecha_exposicion' => 'nullable|date',
            'estado' => 'nullable|in:programada,en_evaluacion,finalizada',
            'presentation_order' => 'nullable|integer|min:0',
                'resultado' => 'nullable|in:pendiente,viable,no_viable',
                'apto_titulacion' => 'nullable|boolean',
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
        $evaluation = Evaluation::with(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher'])->find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        return response()->json($this->shapeEvaluation($evaluation));
    }

    public function update(Request $request, $id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

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
                'apto_titulacion' => 'nullable|boolean',
            ]);

            if (isset($validated['semestre'])) {
                $validated['etapa'] = $this->stageForSemester((int) $validated['semestre']);
            }

            $evaluation->update($validated);
            return response()->json(['message' => 'Evaluacion actualizada', 'evaluation' => $this->shapeEvaluation($evaluation->load(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher']))]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function destroy($id)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        $evaluation = Evaluation::find($id);
        if (!$evaluation) {
            return response()->json(['error' => 'Evaluacion no encontrada'], 404);
        }

        if (!$this->canScoreEvaluation($evaluation, $user)) {
            return response()->json(['error' => 'La evaluacion de este proyecto esta bloqueada hasta que sea su turno en la sala.'], 403);
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
        if (!$this->canScoreEvaluation($evaluation, $user)) {
            return response()->json(['error' => 'La evaluacion de este proyecto esta bloqueada hasta que sea su turno en la sala.'], 403);
        }
        if (!$this->canUserScoreEvaluation($evaluation, $user)) {
            return response()->json(['error' => 'No estas asignado como evaluador de esta sala.'], 403);
        }

        $validCriteria = $this->criteriaForEvaluation($evaluation)->pluck('clave')->all();

        try {
            $validated = $request->validate([
                'scores' => 'required|array',
                'scores.*.criterio' => ['required', 'string', Rule::in($validCriteria)],
                'scores.*.nivel' => ['required', 'string', Rule::in(array_keys($this->levels))],
                'scores.*.comentario' => 'nullable|string',
                'general_comment' => 'nullable|string|max:3000',
                'confirm_update' => 'nullable|boolean',
                'apto_titulacion' => 'nullable|boolean',
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
                    $scoreMode = $this->rubricScoreModeForSemester((int) $evaluation->semestre);
                    EvaluationScore::updateOrCreate(
                        [
                            'evaluation_id' => $evaluation->id,
                            'teacher_id' => $user->id,
                            'criterio' => $score['criterio'],
                        ],
                        [
                            'nivel' => $score['nivel'],
                            'puntaje' => $this->pointsForLevel($score['nivel'], $scoreMode),
                            'comentario' => $score['comentario'] ?? null,
                        ]
                    );
                }
                $attempt->update([
                    'attempts_count' => $attempt->attempts_count + 1,
                    'general_comment' => $validated['general_comment'] ?? $attempt->general_comment,
                    'last_submitted_at' => now(),
                ]);
            });

            if ((int) $evaluation->semestre === 8 && $request->has('apto_titulacion')) {
                $evaluation->update(['apto_titulacion' => $request->boolean('apto_titulacion')]);
            }

            return response()->json([
                'message' => 'Rubrica guardada',
                'evaluation' => $this->shapeEvaluation($evaluation->fresh(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher'])),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function feedback(Request $request, $id)
    {
        $user = auth('api')->user();
        $evaluation = Evaluation::with('room')->findOrFail($id);
        if (!$this->isRoomResponsible($evaluation->room, $user) && !$this->isEvaluationManager($user)) {
            return response()->json(['error' => 'Solo el responsable de la sala o administracion puede registrar retroalimentacion.'], 403);
        }

        $validated = $request->validate([
            'room_feedback' => 'required|string|max:5000',
        ]);

        $evaluation->update([
            'room_feedback' => $validated['room_feedback'],
            'feedback_by' => $user->id,
            'feedback_at' => now(),
        ]);

        return response()->json(['message' => 'Retroalimentacion guardada', 'evaluation' => $this->shapeEvaluation($evaluation->fresh(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher']))]);
    }

    public function projects()
    {
        $user = auth('api')->user();
        $query = Project::with('students:id,nombres,apa,ama,email,semestre,grupo')
            ->where('activo', true)
            ->orderBy('title');
        if ((int) $user->perfil_id === 2 && !$this->isEvaluationManager($user)) {
            $projectIds = Evaluation::where(function ($scope) use ($user) {
                $scope->whereHas('room.teachers', fn ($teacherQuery) => $teacherQuery->where('users.id', $user->id))
                    ->orWhereHas('room', fn ($roomQuery) => $roomQuery->where('responsible_teacher_id', $user->id));
            })->pluck('project_id');
            $query->whereIn('id', $projectIds);
        }
        if (request()->filled('semestre')) {
            $query->where('semestre', request('semestre'));
        }
        return response()->json($query->get([
            'id',
            'title',
            'description',
            'semestre',
            'authors',
            'company_name',
            'company_giro',
            'company_contact_name',
            'company_contact_position',
            'subject_group_id',
            'year',
            'proposal_status',
        ]));
    }

    public function rooms(Request $request)
    {
        $user = auth('api')->user();
        $supportsArchive = Schema::hasColumn('evaluations', 'archived_at');
        $query = EvaluationRoom::with(['teachers:id,nombres,apa,ama,perfil_id', 'responsibleTeacher:id,nombres,apa,ama,perfil_id', 'projects:id,title,semestre,company_name'])
            ->where('activo', !$request->boolean('archived'))
            ->orderByDesc('fecha_evaluacion')
            ->orderBy('nombre');

        if ($supportsArchive && $request->boolean('archived')) {
            $query->whereHas('evaluations', fn ($evaluationQuery) => $evaluationQuery->whereNotNull('archived_at'));
        } elseif ($supportsArchive) {
            $query->whereHas('evaluations', fn ($evaluationQuery) => $evaluationQuery->whereNull('archived_at'));
        }

        if ((int) $user->perfil_id === 2 && !$this->isEvaluationManager($user)) {
            $query->where(function ($scope) use ($user) {
                $scope->whereHas('teachers', fn ($teacherQuery) => $teacherQuery->where('users.id', $user->id))
                    ->orWhere('responsible_teacher_id', $user->id);
            });
        }

        if ($request->filled('semestre')) {
            $query->where('semestre', $request->semestre);
        }

        return response()->json($query->get()->map(fn ($room) => $this->shapeRoom($room)));
    }

    public function storeRoom(Request $request)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $validated = $this->roomRules($request);
        $room = EvaluationRoom::create(collect($validated)->except(['teacher_ids', 'project_ids', 'project_order'])->toArray());
        $room->teachers()->sync($validated['teacher_ids'] ?? []);
        $room->projects()->sync($this->projectSyncPayload($validated['project_ids'] ?? [], $validated['project_order'] ?? []));
        $this->syncRoomEvaluations($room);

        return response()->json(['message' => 'Sala creada', 'room' => $this->shapeRoom($room->load(['teachers', 'projects']))], 201);
    }

    public function updateRoom(Request $request, $id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $room = EvaluationRoom::findOrFail($id);
        $validated = $this->roomRules($request);
        $room->update(collect($validated)->except(['teacher_ids', 'project_ids', 'project_order'])->toArray());
        $room->teachers()->sync($validated['teacher_ids'] ?? []);
        $room->projects()->sync($this->projectSyncPayload($validated['project_ids'] ?? [], $validated['project_order'] ?? []));
        $this->syncRoomEvaluations($room);

        return response()->json(['message' => 'Sala actualizada', 'room' => $this->shapeRoom($room->load(['teachers', 'projects']))]);
    }

    public function destroyRoom($id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        EvaluationRoom::findOrFail($id)->update(['activo' => false]);
        return response()->json(['message' => 'Sala desactivada']);
    }

    public function archiveRoom($id)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        if (!Schema::hasColumn('evaluations', 'archived_at')) {
            return response()->json(['message' => 'La migracion de archivo de evaluaciones aun no esta aplicada.'], 409);
        }

        $room = EvaluationRoom::findOrFail($id);
        $room->update(['activo' => false]);
        Evaluation::where('evaluation_room_id', $room->id)->update([
            'archived_at' => now(),
            'archived_by' => $user->id,
        ]);

        return response()->json(['message' => 'Sala archivada']);
    }

    public function unarchiveRoom($id)
    {
        $user = auth('api')->user();
        if ($guard = $this->guardEvaluationManager($user)) return $guard;

        if (!Schema::hasColumn('evaluations', 'archived_at')) {
            return response()->json(['message' => 'La migracion de archivo de evaluaciones aun no esta aplicada.'], 409);
        }

        $room = EvaluationRoom::findOrFail($id);
        $room->update(['activo' => true]);
        Evaluation::where('evaluation_room_id', $room->id)->update([
            'archived_at' => null,
            'archived_by' => null,
        ]);

        return response()->json(['message' => 'Sala restaurada']);
    }

    public function lockRoomSequence($id)
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $room = EvaluationRoom::with('projects')->findOrFail($id);
        $ordered = $room->projects->sortBy(fn ($project) => (int) ($project->pivot->presentation_order ?: 9999))->values();
        if ($ordered->isEmpty()) {
            throw ValidationException::withMessages(['project_ids' => ['La sala no tiene proyectos asignados.']]);
        }

        $firstOrder = (int) ($ordered->first()->pivot->presentation_order ?: 1);
        DB::transaction(function () use ($room, $ordered, $firstOrder) {
            $room->update([
                'sequence_locked' => true,
                'current_order' => $firstOrder,
                'completed_at' => null,
            ]);

            foreach ($ordered as $project) {
                $order = (int) ($project->pivot->presentation_order ?: 0);
                $status = $order === $firstOrder ? 'activo' : 'pendiente';
                DB::table('evaluation_room_project')
                    ->where('evaluation_room_id', $room->id)
                    ->where('project_id', $project->id)
                    ->update(['status' => $status]);
                Evaluation::where('evaluation_room_id', $room->id)
                    ->where('project_id', $project->id)
                    ->update([
                        'presentation_order' => $order,
                        'sequence_status' => $status,
                        'estado' => $status === 'activo' ? 'en_evaluacion' : 'programada',
                    ]);
            }
        });

        return response()->json(['message' => 'Orden bloqueado. El primer proyecto ya puede evaluarse.', 'room' => $this->shapeRoom($room->fresh(['teachers', 'responsibleTeacher', 'projects']))]);
    }

    public function advanceRoom(Request $request, $id)
    {
        $user = auth('api')->user();
        $room = EvaluationRoom::with('projects')->findOrFail($id);
        if (!$this->isRoomResponsible($room, $user) && !$this->isEvaluationManager($user)) {
            return response()->json(['error' => 'Solo el responsable de la sala puede avanzar el turno.'], 403);
        }

        $validated = $request->validate([
            'continue_next' => 'required|boolean',
        ]);
        if (!$validated['continue_next']) {
            return response()->json(['message' => 'La sala permanece en el proyecto actual.', 'room' => $this->shapeRoom($room->load(['teachers', 'responsibleTeacher', 'projects']))]);
        }

        DB::transaction(function () use ($room) {
            $currentOrder = (int) $room->current_order;
            $ordered = $room->projects->sortBy(fn ($project) => (int) ($project->pivot->presentation_order ?: 9999))->values();
            $next = $ordered->first(fn ($project) => (int) $project->pivot->presentation_order > $currentOrder);

            DB::table('evaluation_room_project')
                ->where('evaluation_room_id', $room->id)
                ->where('presentation_order', $currentOrder)
                ->update(['status' => 'evaluado']);
            Evaluation::where('evaluation_room_id', $room->id)
                ->where('presentation_order', $currentOrder)
                ->update(['sequence_status' => 'evaluado', 'estado' => 'finalizada', 'finalized_at' => now()]);

            if ($next) {
                $nextOrder = (int) $next->pivot->presentation_order;
                DB::table('evaluation_room_project')
                    ->where('evaluation_room_id', $room->id)
                    ->where('project_id', $next->id)
                    ->update(['status' => 'activo']);
                Evaluation::where('evaluation_room_id', $room->id)
                    ->where('project_id', $next->id)
                    ->update(['sequence_status' => 'activo', 'estado' => 'en_evaluacion']);
                $room->update(['current_order' => $nextOrder]);
            } else {
                $room->update(['current_order' => null, 'completed_at' => now()]);
            }
        });

        return response()->json(['message' => 'Turno actualizado', 'room' => $this->shapeRoom($room->fresh(['teachers', 'responsibleTeacher', 'projects']))]);
    }

    public function exportEvaluationPdf($id)
    {
        $evaluation = $this->reportEvaluationQuery()->findOrFail($id);
        if ($guard = $this->guardEvaluationReport($evaluation)) return $guard;

        if (!app()->bound('dompdf.wrapper') && !class_exists(\Dompdf\Dompdf::class)) {
            return response()->json([
                'error' => 'DomPDF no esta instalado. Ejecuta composer install o instala barryvdh/laravel-dompdf en el despliegue.',
            ], 501);
        }

        $report = $this->evaluationReportData($evaluation);
        $pdf = app('dompdf.wrapper');
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $pdf->loadHTML(view('reports.evaluation-pdf', $report)->render());

        return $pdf->download('reporte_evaluacion_' . $evaluation->id . '.pdf');
    }

    public function exportRoomPdf($id)
    {
        $room = $this->reportRoomQuery()->findOrFail($id);
        if ($guard = $this->guardRoomReport($room)) return $guard;

        if (!app()->bound('dompdf.wrapper') && !class_exists(\Dompdf\Dompdf::class)) {
            return response()->json([
                'error' => 'DomPDF no esta instalado. Ejecuta composer install o instala barryvdh/laravel-dompdf en el despliegue.',
            ], 501);
        }

        $report = $this->roomReportData($room);
        $pdf = app('dompdf.wrapper');
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $pdf->loadHTML(view('reports.room-pdf', $report)->render());

        return $pdf->download('reporte_sala_' . $room->id . '.pdf');
    }

    public function managers()
    {
        if ($guard = $this->guardEvaluationManager()) return $guard;

        $managerIds = $this->evaluationManagerIds();
        $teachers = User::where('perfil_id', 2)
            ->where('activo', true)
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apa', 'ama', 'email']);

        return response()->json([
            'manager_ids' => $managerIds,
            'teachers' => $teachers,
        ]);
    }

    private function reportEvaluationQuery()
    {
        return Evaluation::with([
            'project.students',
            'project.advisors',
            'room.teachers',
            'room.responsibleTeacher',
            'scores.teacher',
            'attempts.teacher',
        ]);
    }

    private function reportRoomQuery()
    {
        return EvaluationRoom::with([
            'teachers:id,nombres,apa,ama,perfil_id',
            'responsibleTeacher:id,nombres,apa,ama,perfil_id',
            'evaluations.project.students',
            'evaluations.project.advisors',
            'evaluations.scores.teacher',
            'evaluations.attempts.teacher',
        ]);
    }

    private function guardEvaluationReport(Evaluation $evaluation)
    {
        $user = auth('api')->user();
        if ($this->isEvaluationManager($user) || $this->isRoomResponsible($evaluation->room, $user)) {
            return null;
        }

        if ((int) $user->perfil_id === 2 && $this->canUserScoreEvaluation($evaluation, $user)) {
            return null;
        }

        return response()->json(['error' => 'No autorizado para exportar esta evaluacion.'], 403);
    }

    private function guardRoomReport(EvaluationRoom $room)
    {
        $user = auth('api')->user();
        if ($this->isEvaluationManager($user) || $this->isRoomResponsible($room, $user)) {
            return null;
        }

        $teacherIds = $room->teachers->pluck('id')->map(fn ($id) => (string) $id);
        if ((int) $user->perfil_id === 2 && $teacherIds->contains((string) $user->id)) {
            return null;
        }

        return response()->json(['error' => 'No autorizado para exportar esta sala.'], 403);
    }

    private function evaluationReportData(Evaluation $evaluation): array
    {
        $scores = $this->activeScoresForEvaluation($evaluation);
        $labels = $this->criteriaLabelsForEvaluation($evaluation);
        $orderedCriteria = $this->criteriaForEvaluation($evaluation)
            ->pluck('pregunta', 'clave')
            ->all();
        $criteriaLabels = array_replace($orderedCriteria, $labels);

        $teachers = $scores
            ->map(fn ($score) => $score->teacher)
            ->filter()
            ->unique('id')
            ->values();

        $matrix = collect($criteriaLabels)->map(function ($label, $criterion) use ($evaluation, $teachers, $scores) {
            $teacherScores = [];
            $percentages = [];

            foreach ($teachers as $teacher) {
                $score = $scores
                    ->where('criterio', $criterion)
                    ->firstWhere('teacher_id', $teacher->id);

                if ($score) {
                    $percentage = $this->scorePercentage($score, (int) $evaluation->semestre);
                    $percentages[] = $percentage;
                    $teacherScores[] = [
                        'teacher_id' => $teacher->id,
                        'value' => $score->puntaje . '/' . $this->maxScoreForScore($score, (int) $evaluation->semestre),
                        'percentage' => $percentage,
                        'level' => $this->levelLabels[$score->nivel] ?? $score->nivel,
                    ];
                } else {
                    $teacherScores[] = [
                        'teacher_id' => $teacher->id,
                        'value' => '-',
                        'percentage' => null,
                        'level' => '-',
                    ];
                }
            }

            return [
                'criterion' => $criterion,
                'label' => $label,
                'teacher_scores' => $teacherScores,
                'average' => count($percentages) ? round(array_sum($percentages) / count($percentages), 2) : 0,
            ];
        })->values()->all();

        $comments = $teachers->map(function ($teacher) use ($evaluation, $scores) {
            $attempt = $evaluation->attempts->firstWhere('teacher_id', $teacher->id);
            $criterionComments = $scores
                ->where('teacher_id', $teacher->id)
                ->filter(fn ($score) => filled($score->comentario))
                ->map(fn ($score) => [
                    'criterion' => $this->criteriaLabelsForEvaluation($evaluation)[$score->criterio] ?? $score->criterio,
                    'comment' => $score->comentario,
                ])
                ->values()
                ->all();

            return [
                'teacher_id' => $teacher->id,
                'teacher_name' => $this->fullName($teacher),
                'general_comment' => $attempt?->general_comment,
                'criterion_comments' => $criterionComments,
            ];
        })->values()->all();

        $chartLabels = collect($matrix)->pluck('label')->map(fn ($label) => Str::limit($label, 32))->all();
        $chartValues = collect($matrix)->pluck('average')->all();

        return [
            'evaluation' => $evaluation,
            'project' => $evaluation->project,
            'students' => $evaluation->project?->students ?? collect(),
            'teachers' => $teachers,
            'matrix' => $matrix,
            'comments' => $comments,
            'globalAverage' => $this->scoreCollectionAverage($scores, (int) $evaluation->semestre),
            'chartUrl' => $this->quickChartUrl($chartLabels, $chartValues),
            'generatedAt' => now(),
        ];
    }

    private function roomReportData(EvaluationRoom $room): array
    {
        $evaluations = $room->evaluations
            ->sortBy(fn ($evaluation) => (int) ($evaluation->presentation_order ?? 0))
            ->values();
        $evaluationReports = $evaluations
            ->map(fn ($evaluation) => $this->evaluationReportData($evaluation))
            ->values();
        $evaluated = $evaluations->filter(fn ($evaluation) => $evaluation->scores->isNotEmpty());

        $projectRows = $evaluationReports->map(function ($report) {
            $evaluation = $report['evaluation'];
            $project = $report['project'];

            return [
                'evaluation_id' => $evaluation->id,
                'order' => $evaluation->presentation_order,
                'project_title' => $project?->title ?? 'Proyecto sin titulo',
                'students' => $report['students']->map(fn ($student) => $this->fullName($student))->filter()->join(', '),
                'teachers_count' => $report['teachers']->count(),
                'average' => $report['globalAverage'],
                'status' => $evaluation->sequence_status ?? $evaluation->estado,
                'result' => $evaluation->resultado,
            ];
        })->all();

        $chartLabels = collect($projectRows)->pluck('project_title')->map(fn ($title) => Str::limit($title, 30))->all();
        $chartValues = collect($projectRows)->pluck('average')->all();

        return [
            'room' => $room,
            'evaluations' => $evaluations,
            'evaluationReports' => $evaluationReports,
            'projectRows' => $projectRows,
            'roomAverage' => $evaluated->count()
                ? round($evaluated->avg(fn ($evaluation) => $this->scoreCollectionAverage($evaluation->scores, (int) $evaluation->semestre)), 2)
                : 0,
            'chartUrl' => $this->quickChartUrl($chartLabels, $chartValues),
            'generatedAt' => now(),
        ];
    }

    private function quickChartUrl(array $labels, array $values): string
    {
        $config = [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Promedio por criterio',
                    'data' => $values,
                    'backgroundColor' => '#2563eb',
                ]],
            ],
            'options' => [
                'legend' => ['display' => false],
                'scales' => [
                    'yAxes' => [[
                        'ticks' => ['beginAtZero' => true, 'max' => 100],
                    ]],
                ],
            ],
        ];

        return 'https://quickchart.io/chart?' . http_build_query([
            'width' => 760,
            'height' => 320,
            'format' => 'png',
            'c' => json_encode($config),
        ]);
    }

    private function writePowerBiEvaluationRow(
        mixed $handle,
        EvaluationRoom $room,
        Evaluation $evaluation,
        ?User $teacher = null,
        ?float $teacherAverage = null,
        ?EvaluationScore $score = null,
        ?string $teacherGeneralComment = null,
        ?string $criterionLabel = null
    ): void {
        $students = $evaluation->project?->students ?? collect();

        fputcsv($handle, [
            $room->id,
            $this->csvCell($room->nombre),
            $this->csvCell($room->salon),
            $room->semestre,
            optional($room->fecha_evaluacion)->format('Y-m-d H:i:s'),
            $evaluation->presentation_order,
            $evaluation->id,
            $evaluation->project_id,
            $this->csvCell($evaluation->project?->title),
            $this->csvCell($students->map(fn ($student) => $this->fullName($student))->filter()->join(' | ')),
            $students->count(),
            $this->csvCell($evaluation->sequence_status),
            $this->csvCell($evaluation->estado),
            $this->csvCell($evaluation->resultado),
            $this->scoreCollectionAverage($evaluation->scores, (int) $evaluation->semestre),
            $teacher?->id,
            $this->csvCell($teacher ? $this->fullName($teacher) : null),
            $teacherAverage,
            $this->csvCell($score?->criterio),
            $this->csvCell($criterionLabel),
            $this->csvCell($score ? ($this->levelLabels[$score->nivel] ?? $score->nivel) : null),
            $score?->puntaje,
            $score ? $this->scorePercentage($score, (int) $evaluation->semestre) : null,
            $this->csvCell($score?->comentario),
            $this->csvCell($teacherGeneralComment),
            $this->csvCell($evaluation->room_feedback),
            optional($evaluation->feedback_at)->format('Y-m-d H:i:s'),
            $evaluation->apto_titulacion === null ? null : ($evaluation->apto_titulacion ? 1 : 0),
        ], ';');
    }

    private function fullName(User $user): string
    {
        return trim(collect([$user->nombres, $user->apa, $user->ama])->filter()->join(' '));
    }

    private function csvCell(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', trim($value));
    }

    private function rubricScoreModes(): array
    {
        $modes = SystemSetting::valueFor('rubric_score_modes', []);
        $defaults = ['5' => 'levels', '6' => 'levels', '7' => 'levels', '8' => 'levels'];

        return array_replace($defaults, array_intersect_key((array) $modes, $defaults));
    }

    private function rubricScoreModeForSemester(int $semester): string
    {
        return $this->rubricScoreModes()[(string) $semester] ?? 'levels';
    }

    private function maxScoreForSemester(int $semester): int
    {
        return $this->rubricScoreModeForSemester($semester) === 'numeric' ? 5 : 4;
    }

    private function pointsForLevel(string $level, string $mode): int
    {
        if ($mode === 'numeric') {
            return match ($level) {
                'totalmente_en_desacuerdo' => 1,
                'en_desacuerdo' => 2,
                'neutral' => 3,
                'de_acuerdo' => 4,
                'totalmente_de_acuerdo' => 5,
                default => 1,
            };
        }

        return $this->levels[$level] ?? 0;
    }

    private function isLegacyLevel(?string $level): bool
    {
        return $level !== null && array_key_exists($level, $this->legacyLevels);
    }

    private function maxScoreForScore(EvaluationScore $score, int $semester): int
    {
        if ($this->isLegacyLevel($score->nivel)) {
            return 3;
        }

        return $this->maxScoreForSemester($semester);
    }

    private function scoreModeForScore(EvaluationScore $score, int $semester): string
    {
        if ($this->isLegacyLevel($score->nivel)) {
            return 'legacy';
        }

        return $this->rubricScoreModeForSemester($semester);
    }

    private function scorePercentage(EvaluationScore $score, int $semester): float
    {
        $maxScore = max(1, $this->maxScoreForScore($score, $semester));

        return round(($score->puntaje / $maxScore) * 100, 2);
    }

    private function scoreCollectionAverage($scores, int $semester): float
    {
        if (!$scores || $scores->count() === 0) {
            return 0;
        }

        return round($scores->avg(fn ($score) => $this->scorePercentage($score, $semester)), 2);
    }

    public function updateManagers(Request $request)
    {
        $user = auth('api')->user();
        if ((int) $user->perfil_id !== 1) {
            return response()->json(['error' => 'Solo administracion puede asignar responsables de evaluaciones.'], 403);
        }

        $validated = $request->validate([
            'teacher_ids' => 'present|array',
            'teacher_ids.*' => ['string', Rule::exists('users', 'id')->where('activo', true)->where('perfil_id', 2)],
        ]);

        $teacherIds = collect($validated['teacher_ids'])
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        SystemSetting::setValue('evaluation_manager_teacher_ids', $teacherIds, 'array', 'Docentes con acceso completo a gestion de evaluaciones');

        return response()->json([
            'message' => 'Responsables de evaluaciones actualizados',
            'manager_ids' => $teacherIds,
        ]);
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
            'project_id' => $criterion->project_id,
            'scope' => $criterion->project_id ? 'project' : 'general',
            'key' => $criterion->clave,
            'label' => $criterion->pregunta,
            'orden' => $criterion->orden,
        ];
    }

    private function criteriaForEvaluation(Evaluation $evaluation)
    {
        return RubricCriterion::where('semestre', $evaluation->semestre)
            ->where('activo', true)
            ->where(function ($query) use ($evaluation) {
                $query->whereNull('project_id');
                if ((int) $evaluation->semestre === 8 && $evaluation->project_id) {
                    $query->orWhere('project_id', $evaluation->project_id);
                }
            })
            ->orderByRaw('CASE WHEN project_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    private function activeScoresForEvaluation(Evaluation $evaluation)
    {
        $activeCriteria = $this->criteriaForEvaluation($evaluation)->pluck('clave');

        return $evaluation->scores->whereIn('criterio', $activeCriteria);
    }

    private function criteriaLabelsForEvaluation(Evaluation $evaluation): array
    {
        $cacheKey = (int) $evaluation->semestre . ':' . ((int) $evaluation->project_id ?: 'general');
        if (isset($this->criteriaLabelCache[$cacheKey])) {
            return $this->criteriaLabelCache[$cacheKey];
        }

        return $this->criteriaLabelCache[$cacheKey] = $this->criteriaForEvaluation($evaluation)
            ->pluck('pregunta', 'clave')
            ->all();
    }

    private function criteriaLabelsForSemester(int $semester): array
    {
        if (isset($this->criteriaLabelCache[$semester])) {
            return $this->criteriaLabelCache[$semester];
        }

        return $this->criteriaLabelCache[$semester] = RubricCriterion::where('semestre', $semester)
            ->where('activo', true)
            ->pluck('pregunta', 'clave')
            ->all();
    }

    private function shapeEvaluation(Evaluation $evaluation): array
    {
        $scores = $this->activeScoresForEvaluation($evaluation);
        $labels = $this->criteriaLabelsForEvaluation($evaluation);
        $scoreMode = $this->rubricScoreModeForSemester((int) $evaluation->semestre);
        $maxScore = $this->maxScoreForSemester((int) $evaluation->semestre);
        $globalAverage = $this->scoreCollectionAverage($scores, (int) $evaluation->semestre);

        $teacherBreakdown = $scores
            ->groupBy('teacher_id')
            ->map(function ($teacherScores) use ($labels, $evaluation) {
                $teacher = $teacherScores->first()->teacher;
                $attempt = $evaluation->attempts?->firstWhere('teacher_id', $teacher?->id);
                return [
                    'teacher_id' => $teacher?->id,
                    'teacher_name' => trim(($teacher?->nombres ?? '') . ' ' . ($teacher?->apa ?? '') . ' ' . ($teacher?->ama ?? '')) ?: 'Docente',
                    'average' => $this->scoreCollectionAverage($teacherScores, (int) $evaluation->semestre),
                    'score_mode' => $teacherScores->every(fn ($score) => $this->isLegacyLevel($score->nivel))
                        ? 'legacy'
                        : $this->rubricScoreModeForSemester((int) $evaluation->semestre),
                    'max_score' => $teacherScores->every(fn ($score) => $this->isLegacyLevel($score->nivel))
                        ? 3
                        : $this->maxScoreForSemester((int) $evaluation->semestre),
                    'general_comment' => $attempt?->general_comment,
                    'scores' => $teacherScores->map(fn ($score) => [
                        'criterio' => $score->criterio,
                        'criterio_label' => $labels[$score->criterio] ?? $score->criterio,
                        'nivel' => $score->nivel,
                        'nivel_label' => $this->levelLabels[$score->nivel] ?? $score->nivel,
                        'puntaje' => $score->puntaje,
                        'puntaje_max' => $this->maxScoreForScore($score, (int) $evaluation->semestre),
                        'score_mode' => $this->scoreModeForScore($score, (int) $evaluation->semestre),
                        'comentario' => $score->comentario,
                    ])->values(),
                ];
            })
            ->values();
        $expectedEvaluatorIds = $evaluation->room
            ? $evaluation->room->teachers->pluck('id')->map(fn ($id) => (string) $id)->unique()->values()
            : collect();
        $completedEvaluatorIds = $teacherBreakdown->pluck('teacher_id')->filter()->map(fn ($id) => (string) $id)->unique()->values();
        $expectedEvaluatorsCount = $expectedEvaluatorIds->count();
        $evaluatedByAll = $expectedEvaluatorsCount > 0 && $expectedEvaluatorIds->diff($completedEvaluatorIds)->isEmpty();

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
            'presentation_order' => $evaluation->presentation_order,
            'sequence_status' => $evaluation->sequence_status,
            'estado' => $evaluation->estado,
            'resultado' => $evaluation->resultado,
            'room_feedback' => $evaluation->room_feedback,
            'feedback_at' => optional($evaluation->feedback_at)->toDateTimeString(),
            'archived_at' => optional($evaluation->archived_at)->toDateTimeString(),
            'archived_by' => $evaluation->archived_by,
            'apto_titulacion' => $evaluation->apto_titulacion,
            'global_average' => $globalAverage,
            'score_mode' => $scoreMode,
            'max_score' => $maxScore,
            'global_average_color' => $globalAverage < 70 ? 'danger' : ($globalAverage <= 85 ? 'warning' : 'success'),
            'evaluators_count' => $teacherBreakdown->count(),
            'expected_evaluators_count' => $expectedEvaluatorsCount,
            'evaluated_by_all' => $evaluatedByAll,
            'evaluation_badge_color' => $evaluatedByAll ? 'success' : ($evaluation->sequence_status === 'activo' ? 'primary' : 'secondary'),
            'teacher_breakdown' => $teacherBreakdown,
            'current_teacher_attempts' => optional($evaluation->attempts->firstWhere('teacher_id', auth('api')->id()))->attempts_count ?? 0,
            'current_teacher_has_scores' => $scores->where('teacher_id', auth('api')->id())->isNotEmpty(),
            'max_attempts' => $evaluation->room?->max_attempts ?? 1,
            'can_score_now' => $this->canScoreEvaluation($evaluation, auth('api')->user()),
            'is_room_responsible' => $this->isRoomResponsible($evaluation->room, auth('api')->user()),
            'can_manage_evaluations' => $this->isEvaluationManager(auth('api')->user()),
        ];
    }

    private function roomRules(Request $request): array
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:80',
            'salon' => 'nullable|string|max:120',
            'semestre' => 'required|integer|in:5,6,7,8',
            'responsible_teacher_id' => ['nullable', Rule::exists('users', 'id')->where('activo', true)->whereIn('perfil_id', [1, 2])],
            'fecha_evaluacion' => 'required|date|after:now',
            'teacher_evaluation_minutes' => 'required|integer|min:1|max:240',
            'project_presentation_minutes' => 'required|integer|min:1|max:240',
            'max_attempts' => 'required|integer|min:1|max:10',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => ['string', Rule::exists('users', 'id')->where('activo', true)->whereIn('perfil_id', [1, 2])],
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|distinct|exists:projects,id',
            'project_order' => 'nullable|array',
            'project_order.*' => 'integer|min:1|distinct',
        ]);

        $ignoreId = $request->route('id');
        $duplicateName = $this->schedulableRoomQuery()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($validated['nombre'])])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
        if ($duplicateName) {
            throw ValidationException::withMessages(['nombre' => ['Ya existe una sala activa con ese nombre.']]);
        }

        $projectIds = collect($validated['project_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($projectIds->count() !== count($validated['project_ids'] ?? [])) {
            throw ValidationException::withMessages(['project_ids' => ['No puedes asignar el mismo proyecto mas de una vez en la sala.']]);
        }

        $duplicateProjectRoom = $this->schedulableRoomQuery()
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $projectIds))
            ->exists();

        if ($duplicateProjectRoom) {
            throw ValidationException::withMessages([
                'project_ids' => ['Uno o mas proyectos ya estan asignados a otra sala activa.'],
            ]);
        }

        $selectedHour = \Illuminate\Support\Carbon::parse($validated['fecha_evaluacion'])->startOfHour();
        $conflictingRooms = $this->schedulableRoomQuery()
            ->whereBetween('fecha_evaluacion', [$selectedHour, $selectedHour->copy()->endOfHour()])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(function ($query) use ($validated) {
                $teacherIds = $validated['teacher_ids'] ?? [];
                if ($teacherIds) {
                    $query->whereHas('teachers', fn ($q) => $q->whereIn('users.id', $teacherIds));
                    return;
                }
                $query->whereRaw('1 = 0');
            })
            ->with(['teachers:id,nombres,apa,perfil_id', 'projects:id,title'])
            ->get();

        if ($conflictingRooms->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_evaluacion' => ['Hay docentes o proyectos ya asignados en otra sala para la misma fecha y hora.'],
            ]);
        }

        return $validated;
    }

    private function schedulableRoomQuery()
    {
        $query = EvaluationRoom::query()->where('activo', true);

        if (Schema::hasColumn('evaluations', 'archived_at')) {
            $query->where(function ($scope) {
                $scope->whereDoesntHave('evaluations')
                    ->orWhereHas('evaluations', fn ($evaluationQuery) => $evaluationQuery->whereNull('archived_at'));
            });
        }

        return $query;
    }

    private function projectSyncPayload(array $projectIds, array $projectOrder): array
    {
        $payload = [];
        $fallbackOrder = 1;
        foreach (array_values(array_unique(array_map('intval', $projectIds))) as $projectId) {
            $id = (int) $projectId;
            $payload[$id] = [
                'presentation_order' => (int) ($projectOrder[$id] ?? $projectOrder[(string) $id] ?? $fallbackOrder),
                'status' => 'pendiente',
            ];
            $fallbackOrder++;
        }
        uasort($payload, fn ($a, $b) => $a['presentation_order'] <=> $b['presentation_order']);
        return $payload;
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
                    'presentation_order' => (int) ($project->pivot->presentation_order ?: 0),
                    'sequence_status' => $room->sequence_locked
                        ? ((int) $project->pivot->presentation_order === (int) $room->current_order ? 'activo' : ($project->pivot->status ?: 'pendiente'))
                        : 'pendiente',
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
            'responsible_teacher_id' => $room->responsible_teacher_id,
            'responsible_teacher' => $room->responsibleTeacher,
            'fecha_evaluacion' => optional($room->fecha_evaluacion)->toDateTimeString(),
            'teacher_evaluation_minutes' => $room->teacher_evaluation_minutes,
            'project_presentation_minutes' => $room->project_presentation_minutes,
            'max_attempts' => $room->max_attempts,
            'sequence_locked' => $room->sequence_locked,
            'current_order' => $room->current_order,
            'completed_at' => optional($room->completed_at)->toDateTimeString(),
            'teachers' => $room->teachers ?? collect(),
            'projects' => ($room->projects ?? collect())->map(function ($project) {
                $project->presentation_order = (int) ($project->pivot->presentation_order ?? 0);
                $project->sequence_status = $project->pivot->status ?? 'pendiente';
                return $project;
            })->values(),
        ];
    }

    private function canScoreEvaluation(Evaluation $evaluation, $user): bool
    {
        $room = $evaluation->room;
        if (!$room || !$room->sequence_locked) {
            return true;
        }

        return $evaluation->sequence_status === 'activo';
    }

    private function canUserScoreEvaluation(Evaluation $evaluation, $user): bool
    {
        if (!$user || !in_array((int) $user->perfil_id, [1, 2], true)) {
            return false;
        }

        $room = $evaluation->room;
        if (!$room) {
            return true;
        }

        $room->loadMissing('teachers');
        $isAssignedEvaluator = $room->teachers->contains(fn ($teacher) => (string) $teacher->id === (string) $user->id);

        return $isAssignedEvaluator || $this->isEvaluationManager($user);
    }

    private function isRoomResponsible(?EvaluationRoom $room, $user): bool
    {
        return $room && $room->responsible_teacher_id && (string) $room->responsible_teacher_id === (string) $user->id;
    }

    private function evaluationManagerIds(): array
    {
        return collect(SystemSetting::valueFor('evaluation_manager_teacher_ids', []))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function isEvaluationManager($user): bool
    {
        if (!$user) {
            return false;
        }
        if ((int) $user->perfil_id === 1) {
            return true;
        }

        return (int) $user->perfil_id === 2 && in_array((string) $user->id, $this->evaluationManagerIds(), true);
    }

    private function guardEvaluationManager($user = null)
    {
        $user = $user ?: auth('api')->user();
        if ($this->isEvaluationManager($user)) {
            return null;
        }

        return response()->json(['error' => 'No tienes permiso para gestionar evaluaciones.'], 403);
    }
}
