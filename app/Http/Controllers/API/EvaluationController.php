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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    private array $levelLabels = [
        'totalmente_de_acuerdo' => 'Totalmente de acuerdo',
        'de_acuerdo' => 'De acuerdo',
        'neutral' => 'Neutral',
        'en_desacuerdo' => 'En desacuerdo',
        'totalmente_en_desacuerdo' => 'Totalmente en desacuerdo',
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
                'pregunta' => 'required|string|max:255',
                'orden' => 'nullable|integer|min:0',
            ]);

            $baseKey = Str::limit(Str::slug($validated['pregunta'], '_') ?: 'criterio', 64, '');
            $key = $baseKey;
            $suffix = 2;
            while (RubricCriterion::where('semestre', $validated['semestre'])->where('clave', $key)->exists()) {
                $key = Str::limit($baseKey, 64 - strlen((string) $suffix) - 1, '') . '_' . $suffix;
                $suffix++;
            }

            $criterion = RubricCriterion::create([
                'semestre' => $validated['semestre'],
                'clave' => $key,
                'pregunta' => $validated['pregunta'],
                'orden' => $validated['orden'] ?? ((int) RubricCriterion::where('semestre', $validated['semestre'])->max('orden') + 1),
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

            $criterion->update($validated);
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

        $criterion->update(['activo' => false]);
        return response()->json(['message' => 'Pregunta desactivada']);
    }

    public function index(Request $request)
    {
        $user = auth('api')->user();
        $query = Evaluation::with(['project.students', 'room.teachers', 'room.responsibleTeacher', 'scores.teacher', 'attempts.teacher'])
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

        $validCriteria = RubricCriterion::where('semestre', $evaluation->semestre)
            ->where('activo', true)
            ->pluck('clave')
            ->all();

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
        $query = EvaluationRoom::with(['teachers:id,nombres,apa,ama,perfil_id', 'responsibleTeacher:id,nombres,apa,ama,perfil_id', 'projects:id,title,semestre,company_name'])
            ->where('activo', true)
            ->orderByDesc('fecha_evaluacion')
            ->orderBy('nombre');

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

    public function exportRoom($id): StreamedResponse
    {
        if ($guard = $this->guardEvaluationManager()) abort(403, 'No autorizado para exportar evaluaciones.');

        $room = EvaluationRoom::with([
            'teachers:id,nombres,apa,ama,perfil_id',
            'responsibleTeacher:id,nombres,apa,ama,perfil_id',
            'evaluations.project.students',
            'evaluations.scores.teacher',
            'evaluations.attempts.teacher',
        ])->findOrFail($id);
        $filename = 'evaluaciones_sala_' . $room->id . '.xls';

        return response()->streamDownload(function () use ($room) {
            echo $this->roomExcelWorkbook($room);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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

    private function roomExcelWorkbook(EvaluationRoom $room): string
    {
        $evaluations = $room->evaluations->sortBy('presentation_order')->values();
        $summaryRows = $this->roomExcelSummaryRows($room, $evaluations);
        $projectRows = $this->roomExcelProjectRows($room, $evaluations);
        $teacherRows = $this->roomExcelTeacherRows($room, $evaluations);
        $detailRows = $this->roomExcelDetailRows($room, $evaluations);
        $commentRows = $this->roomExcelCommentRows($room, $evaluations);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<?mso-application progid="Excel.Sheet"?>' . "\n"
            . '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
            . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
            . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" '
            . 'xmlns:html="http://www.w3.org/TR/REC-html40">'
            . '<Styles>'
            . '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/></Style>'
            . '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>'
            . '<Style ss:ID="Wrap"><Alignment ss:WrapText="1" ss:Vertical="Top"/></Style>'
            . '</Styles>'
            . $this->excelWorksheet('Resumen', $summaryRows)
            . $this->excelWorksheet('Proyectos', $projectRows)
            . $this->excelWorksheet('Docentes', $teacherRows)
            . $this->excelWorksheet('Detalle_Rubrica', $detailRows)
            . $this->excelWorksheet('Comentarios', $commentRows)
            . '</Workbook>';
    }

    private function roomExcelSummaryRows(EvaluationRoom $room, $evaluations): array
    {
        $evaluated = $evaluations->filter(fn ($evaluation) => $evaluation->scores->isNotEmpty());
        $roomAverage = $evaluated->count() === 0
            ? 0
            : round($evaluated->avg(fn ($evaluation) => $this->scoreCollectionAverage($evaluation->scores, (int) $evaluation->semestre)), 2);

        return [
            ['Reporte de evaluaciones de sala', ''],
            ['Campo', 'Valor'],
            ['sala_id', $room->id],
            ['sala_nombre', $room->nombre],
            ['salon', $room->salon],
            ['semestre', $room->semestre],
            ['fecha_evaluacion', optional($room->fecha_evaluacion)->format('Y-m-d H:i:s')],
            ['responsable', $room->responsibleTeacher ? $this->fullName($room->responsibleTeacher) : ''],
            ['docentes_asignados', $room->teachers->map(fn ($teacher) => $this->fullName($teacher))->filter()->join(' | ')],
            ['total_docentes', $room->teachers->count()],
            ['total_proyectos', $evaluations->count()],
            ['proyectos_con_evaluacion', $evaluated->count()],
            ['promedio_global_sala', $roomAverage],
            ['metodo_puntaje', $this->rubricScoreModeForSemester((int) $room->semestre)],
            ['puntaje_maximo', $this->maxScoreForSemester((int) $room->semestre)],
            ['fecha_exportacion', now()->format('Y-m-d H:i:s')],
        ];
    }

    private function roomExcelProjectRows(EvaluationRoom $room, $evaluations): array
    {
        $rows = [[
            'orden_presentacion',
            'evaluacion_id',
            'proyecto_id',
            'proyecto',
            'integrantes',
            'total_integrantes',
            'empresa',
            'giro',
            'contacto_empresa',
            'estado_turno',
            'estado_evaluacion',
            'resultado',
            'promedio_global',
            'evaluadores_registrados',
            'evaluadores_esperados',
            'evaluado_por_todos',
            'apto_titulacion',
            'retroalimentacion_sala',
        ]];

        foreach ($evaluations as $evaluation) {
            $students = $evaluation->project?->students ?? collect();
            $teacherCount = $evaluation->scores->groupBy('teacher_id')->count();
            $expectedTeacherCount = $room->teachers->count();
            $rows[] = [
                $evaluation->presentation_order,
                $evaluation->id,
                $evaluation->project_id,
                $evaluation->project?->title,
                $students->map(fn ($student) => $this->fullName($student))->filter()->join(' | '),
                $students->count(),
                $evaluation->project?->company_name,
                $evaluation->project?->company_giro,
                $evaluation->project?->company_contact_name,
                $evaluation->sequence_status,
                $evaluation->estado,
                $evaluation->resultado,
                $this->scoreCollectionAverage($evaluation->scores, (int) $evaluation->semestre),
                $teacherCount,
                $expectedTeacherCount,
                $expectedTeacherCount > 0 && $teacherCount >= $expectedTeacherCount ? 'Si' : 'No',
                $evaluation->apto_titulacion === null ? '' : ($evaluation->apto_titulacion ? 'Si' : 'No'),
                $evaluation->room_feedback,
            ];
        }

        return $rows;
    }

    private function roomExcelTeacherRows(EvaluationRoom $room, $evaluations): array
    {
        $rows = [[
            'orden_presentacion',
            'evaluacion_id',
            'proyecto_id',
            'proyecto',
            'docente_id',
            'docente',
            'evaluacion_registrada',
            'criterios_evaluados',
            'promedio_docente',
            'comentario_general_docente',
            'fecha_envio',
        ]];

        foreach ($evaluations as $evaluation) {
            $scoresByTeacher = $evaluation->scores->groupBy('teacher_id');
            $expectedTeachers = $room->teachers->isNotEmpty()
                ? $room->teachers
                : $scoresByTeacher->map(fn ($scores) => $scores->first()->teacher)->filter()->values();

            foreach ($expectedTeachers as $teacher) {
                $teacherId = (string) $teacher->id;
                $scores = $scoresByTeacher->get($teacherId, collect());
                $attempt = $evaluation->attempts->firstWhere('teacher_id', $teacherId);
                $rows[] = [
                    $evaluation->presentation_order,
                    $evaluation->id,
                    $evaluation->project_id,
                    $evaluation->project?->title,
                    $teacherId,
                    $this->fullName($teacher),
                    $scores->isNotEmpty() ? 'Si' : 'No',
                    $scores->count(),
                    $scores->isNotEmpty() ? $this->scoreCollectionAverage($scores, (int) $evaluation->semestre) : '',
                    $attempt?->general_comment,
                    optional($attempt?->last_submitted_at)->format('Y-m-d H:i:s'),
                ];
            }
        }

        return $rows;
    }

    private function roomExcelDetailRows(EvaluationRoom $room, $evaluations): array
    {
        $rows = [[
            'sala_id',
            'sala_nombre',
            'orden_presentacion',
            'evaluacion_id',
            'proyecto_id',
            'proyecto',
            'docente_id',
            'docente',
            'criterio_clave',
            'criterio',
            'nivel',
            'puntaje',
            'puntaje_maximo',
            'puntaje_porcentaje',
            'comentario_criterio',
        ]];

        foreach ($evaluations as $evaluation) {
            $labels = $this->criteriaLabelsForSemester((int) $evaluation->semestre);
            $maxScore = $this->maxScoreForSemester((int) $evaluation->semestre);

            foreach ($evaluation->scores->sortBy(fn ($score) => $score->teacher_id . '|' . $score->criterio) as $score) {
                $rows[] = [
                    $room->id,
                    $room->nombre,
                    $evaluation->presentation_order,
                    $evaluation->id,
                    $evaluation->project_id,
                    $evaluation->project?->title,
                    $score->teacher_id,
                    $score->teacher ? $this->fullName($score->teacher) : '',
                    $score->criterio,
                    $labels[$score->criterio] ?? $score->criterio,
                    $this->levelLabels[$score->nivel] ?? $score->nivel,
                    $score->puntaje,
                    $maxScore,
                    round(($score->puntaje / $maxScore) * 100, 2),
                    $score->comentario,
                ];
            }
        }

        return $rows;
    }

    private function roomExcelCommentRows(EvaluationRoom $room, $evaluations): array
    {
        $rows = [[
            'tipo_comentario',
            'orden_presentacion',
            'evaluacion_id',
            'proyecto_id',
            'proyecto',
            'docente_id',
            'docente',
            'criterio',
            'comentario',
        ]];

        foreach ($evaluations as $evaluation) {
            if ($evaluation->room_feedback) {
                $rows[] = [
                    'retroalimentacion_sala',
                    $evaluation->presentation_order,
                    $evaluation->id,
                    $evaluation->project_id,
                    $evaluation->project?->title,
                    '',
                    '',
                    '',
                    $evaluation->room_feedback,
                ];
            }

            foreach ($evaluation->attempts as $attempt) {
                if (!$attempt->general_comment) {
                    continue;
                }
                $rows[] = [
                    'comentario_general_docente',
                    $evaluation->presentation_order,
                    $evaluation->id,
                    $evaluation->project_id,
                    $evaluation->project?->title,
                    $attempt->teacher_id,
                    $attempt->teacher ? $this->fullName($attempt->teacher) : '',
                    '',
                    $attempt->general_comment,
                ];
            }

            $labels = $this->criteriaLabelsForSemester((int) $evaluation->semestre);
            foreach ($evaluation->scores->filter(fn ($score) => !empty($score->comentario)) as $score) {
                $rows[] = [
                    'comentario_criterio',
                    $evaluation->presentation_order,
                    $evaluation->id,
                    $evaluation->project_id,
                    $evaluation->project?->title,
                    $score->teacher_id,
                    $score->teacher ? $this->fullName($score->teacher) : '',
                    $labels[$score->criterio] ?? $score->criterio,
                    $score->comentario,
                ];
            }
        }

        return $rows;
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
            $score ? round(($score->puntaje / $this->maxScoreForSemester((int) $evaluation->semestre)) * 100, 2) : null,
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

    private function excelWorksheet(string $name, array $rows): string
    {
        $xml = '<Worksheet ss:Name="' . $this->xmlEscape($this->excelSheetName($name)) . '"><Table>';

        foreach ($rows as $index => $row) {
            $style = $index === 0 ? 'Header' : 'Wrap';
            if ($name === 'Resumen' && $index === 0) {
                $style = 'Title';
            } elseif ($name === 'Resumen' && $index === 1) {
                $style = 'Header';
            }

            $xml .= '<Row>';
            foreach ($row as $cell) {
                $xml .= $this->excelCell($cell, $style);
            }
            $xml .= '</Row>';
        }

        return $xml . '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane></WorksheetOptions></Worksheet>';
    }

    private function excelCell(mixed $value, string $style = 'Wrap'): string
    {
        if ($value === null) {
            $value = '';
        }

        $type = is_int($value) || is_float($value) ? 'Number' : 'String';
        $text = $type === 'Number' ? (string) $value : $this->xmlEscape((string) $value);

        return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="' . $type . '">' . $text . '</Data></Cell>';
    }

    private function excelSheetName(string $name): string
    {
        $clean = preg_replace('/[\[\]\*\/\\\\\?:]/', '_', $name) ?: 'Hoja';
        return mb_substr($clean, 0, 31);
    }

    private function xmlEscape(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
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

    private function scoreCollectionAverage($scores, int $semester): float
    {
        if (!$scores || $scores->count() === 0) {
            return 0;
        }

        return round(($scores->avg('puntaje') / $this->maxScoreForSemester($semester)) * 100, 2);
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
            'key' => $criterion->clave,
            'label' => $criterion->pregunta,
            'orden' => $criterion->orden,
        ];
    }

    private function criteriaLabelsForSemester(int $semester): array
    {
        if (isset($this->criteriaLabelCache[$semester])) {
            return $this->criteriaLabelCache[$semester];
        }

        return $this->criteriaLabelCache[$semester] = RubricCriterion::where('semestre', $semester)
            ->pluck('pregunta', 'clave')
            ->all();
    }

    private function shapeEvaluation(Evaluation $evaluation): array
    {
        $scores = $evaluation->scores;
        $labels = $this->criteriaLabelsForSemester($evaluation->semestre);
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
                    'score_mode' => $this->rubricScoreModeForSemester((int) $evaluation->semestre),
                    'max_score' => $this->maxScoreForSemester((int) $evaluation->semestre),
                    'general_comment' => $attempt?->general_comment,
                    'scores' => $teacherScores->map(fn ($score) => [
                        'criterio' => $score->criterio,
                        'criterio_label' => $labels[$score->criterio] ?? $score->criterio,
                        'nivel' => $score->nivel,
                        'nivel_label' => $this->levelLabels[$score->nivel] ?? $score->nivel,
                        'puntaje' => $score->puntaje,
                        'puntaje_max' => $this->maxScoreForSemester((int) $evaluation->semestre),
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
        $duplicateName = EvaluationRoom::where('activo', true)
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

        $duplicateProjectRoom = EvaluationRoom::where('activo', true)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $projectIds))
            ->exists();

        if ($duplicateProjectRoom) {
            throw ValidationException::withMessages([
                'project_ids' => ['Uno o mas proyectos ya estan asignados a otra sala activa.'],
            ]);
        }

        $selectedHour = \Illuminate\Support\Carbon::parse($validated['fecha_evaluacion'])->startOfHour();
        $conflictingRooms = EvaluationRoom::where('activo', true)
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
