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
        $activePeriod = $this->semesterService->activePeriod();
        $periods = AcademicPeriod::query()
            ->withCount('subjectGroups')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();

        $exceptions = \App\Support\CareerScope::constrainOptional(DB::table('autorizaciones_academicas'), 'autorizaciones_academicas', app(\App\Services\AcademicWorkflowService::class)->careerId())
            ->orderByDesc('id')->get();
        $states = DB::table('carrera_periodos')->where('carrera_id', app(\App\Services\AcademicWorkflowService::class)->careerId())->get()->keyBy('periodo_id');
        $periods->each(function ($period) use ($states) {
            $period->operacion = $states->get($period->id);
        });

        return response()->json([
            'scheduler' => DB::table('ejecuciones_academicas')->orderByDesc('id')->first(),
            'active_period_id' => $activePeriod?->id,
            'periods' => $periods,
            'exceptions' => $exceptions,
            'stats' => [
                'students' => User::students()->where('activo', true)->count(),
                'projects' => Project::where('activo', true)->count(),
                'groups' => SubjectGroup::where('activo', true)->whereIn('semestre', $this->semesterService->careerSemesters())->count(),
                'exceptions' => $exceptions->count(),
            ],
        ]);
    }

    /** Semestres válidos para la carrera y el periodo abierto (enero–junio pares, julio–diciembre impares). */
    public function policy()
    {
        $period = rescue(fn () => $this->semesterService->activePeriod(), null, false);

        return response()->json([
            'basic_semesters_enabled' => $this->semesterService->basicSemestersEnabled(),
            'career_semesters' => $this->semesterService->careerSemesters(),
            'period' => $period?->only(['id', 'nombre', 'fecha_inicio', 'fecha_fin']),
            'half' => $period ? $this->semesterService->halfOfYear($period) : null,
            'period_semesters' => $period ? $this->semesterService->semestersForPeriod($period) : $this->semesterService->careerSemesters(),
        ]);
    }

    /**
     * Crea en el periodo destino los grupos del semestre siguiente (6° A → 7° A) para que la promoción
     * encuentre su grupo. Respeta la regla par/impar del destino; 9° no avanza.
     */
    public function generateGroups(AcademicPeriod $period)
    {
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $workflow->manager();
        $source = $workflow->period();
        abort_unless($source && $period->fecha_inicio > $source->fecha_inicio, 422, 'Selecciona un periodo posterior al periodo abierto.');
        abort_if(DB::table('carrera_periodos')->where('carrera_id', $workflow->careerId())->where('periodo_id', $period->id)
            ->whereIn('estado', ['abierto', 'en_cierre', 'cerrado'])->exists(), 409, 'El periodo destino ya inició; crea sus grupos desde Asignaturas.');

        $allowed = $this->semesterService->semestersForPeriod($period);
        $pairs = \App\Support\CareerScope::enrollments($workflow->careerId(), $source->id)->where('m.estado', 'activa')
            ->select('m_grupo.semestre', 'm_grupo.clave_grupo')->distinct()->get();

        $created = DB::transaction(function () use ($pairs, $allowed, $period) {
            $created = [];
            foreach ($pairs as $pair) {
                $next = (int) $pair->semestre + 1;
                if (!in_array($next, $allowed, true)) continue;
                $exists = SubjectGroup::where('periodo_id', $period->id)->where('semestre', $next)->where('grupo', $pair->clave_grupo)->exists();
                if ($exists) continue;
                $created[] = SubjectGroup::create([
                    'nombre' => "{$next}° {$pair->clave_grupo}", 'semestre' => $next, 'grupo' => $pair->clave_grupo,
                    'periodo_id' => $period->id, 'activo' => true,
                ])->nombre;
            }
            return $created;
        });
        $workflow->audit('periodo.grupos_generados', 'periodo', $period->id, null, ['grupos' => $created]);

        return response()->json(['message' => $created ? 'Grupos generados' : 'Los grupos destino ya existen', 'created' => $created]);
    }

    /** Las primeras 8 semanas de cada periodo se pregunta semanalmente por alumnos que cursan otro semestre. */
    public function irregularCheck()
    {
        $user = auth('api')->user();
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $period = rescue(fn () => $workflow->period(), null, false);
        $week = $period ? $this->irregularWeek($period) : null;
        $history = $period ? DB::table('revisiones_irregulares as r')->leftJoin('usuarios as u', 'u.id', '=', 'r.respondido_por')
            ->where('r.carrera_id', $workflow->careerId())->where('r.periodo_id', $period->id)->orderBy('r.semana')
            ->get(['r.semana', 'r.respuesta', 'r.comentario', 'r.creado_en', 'u.nombres as respondido_por']) : collect();
        $answered = $week && $history->contains('semana', $week);

        return response()->json([
            'pending' => (bool) ($week && !$answered && $this->answersIrregularCheck($user)),
            'week' => $week,
            'total_weeks' => self::IRREGULAR_CHECK_WEEKS,
            'period' => $period?->only(['id', 'nombre', 'fecha_inicio']),
            'period_semesters' => $period ? $this->semesterService->semestersForPeriod($period) : [],
            'groups' => $period ? SubjectGroup::where('periodo_id', $period->id)->where('activo', true)->orderBy('semestre')->orderBy('grupo')
                ->get(['id', 'nombre', 'semestre', 'grupo']) : [],
            'history' => $history,
        ]);
    }

    public function answerIrregularCheck(Request $request)
    {
        $user = auth('api')->user();
        abort_unless($this->answersIrregularCheck($user), 403, 'Sólo la jefatura o administración de la carrera responde esta revisión.');
        $data = $request->validate([
            'respuesta' => 'required|in:sin_casos,con_casos',
            'comentario' => 'nullable|required_if:respuesta,con_casos|string|max:500',
        ], ['comentario.required_if' => 'Indica qué alumnos y a qué semestre se movieron.']);
        $workflow = app(\App\Services\AcademicWorkflowService::class);
        $period = $workflow->period();
        $week = $period ? $this->irregularWeek($period) : null;
        abort_unless($week, 409, 'La revisión sólo aplica en las primeras 8 semanas del periodo abierto.');

        $inserted = DB::table('revisiones_irregulares')->insertOrIgnore([
            'carrera_id' => $workflow->careerId(), 'periodo_id' => $period->id, 'semana' => $week,
            'respuesta' => $data['respuesta'], 'comentario' => $data['comentario'] ?? null, 'respondido_por' => $user->id, 'creado_en' => now(),
        ]);
        abort_unless($inserted, 409, 'La revisión de esta semana ya fue respondida.');
        $workflow->audit('periodo.revision_irregulares', 'periodo', $period->id, null, $data + ['semana' => $week]);

        return response()->json(['message' => 'Revisión registrada', 'week' => $week]);
    }

    public const IRREGULAR_CHECK_WEEKS = 8;

    private function irregularWeek(AcademicPeriod $period): ?int
    {
        if (!$period->fecha_inicio || today()->lt($period->fecha_inicio)) return null;
        $week = intdiv((int) $period->fecha_inicio->diffInDays(today()), 7) + 1;

        return $week <= self::IRREGULAR_CHECK_WEEKS ? $week : null;
    }

    private function answersIrregularCheck($user): bool
    {
        return $user && ($user->isGeneralAdmin() || in_array(app(\App\Support\CareerContext::class)->profileId(), [1, 5, 6], true));
    }

    public function storePeriod(Request $request)
    {
        abort_unless(auth('api')->user()->isGeneralAdmin(), 403, 'El calendario es institucional.');
        $validated = $this->validatePeriod($request);
        $period = AcademicPeriod::create($validated + ['activo' => false]);

        return response()->json(['message' => 'Periodo creado', 'period' => $period], 201);
    }

    public function updatePeriod(Request $request, AcademicPeriod $period)
    {
        abort_unless(auth('api')->user()->isGeneralAdmin(), 403, 'El calendario es institucional.');
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
        return response()->json(app(\App\Services\AcademicPromotionService::class)->preview($period));
    }

    public function applyPromotion(Request $request, AcademicPeriod $period)
    {
        $data = $request->validate([
            'operation_id' => 'required|uuid', 'snapshot' => 'required|string|size:64',
            'movements' => 'present|array', 'movements.*.student_id' => 'required|string|distinct',
            'movements.*.action' => 'required|in:avance,permanencia,salida',
            'movements.*.group_id' => 'nullable|integer', 'movements.*.reason' => 'required|string|max:500',
        ]);
        return response()->json(['message' => 'Lote aplicado', 'summary' => app(\App\Services\AcademicPromotionService::class)->confirm($period, $data)]);
    }

    public function transition(Request $request, AcademicPeriod $period)
    {
        $data = $request->validate(['state' => 'required|in:abierto,en_cierre,cerrado']);
        app(\App\Services\AcademicPeriodLifecycleService::class)->transition($period, $data['state']);
        return response()->json(['message' => 'Estado actualizado']);
    }

    public function search(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['students' => [], 'projects' => []]);
        }

        $students = User::query()
            ->students()
            ->where('activo', true)
            ->tap(fn ($query) => \App\Support\TextSearch::apply($query, $term, ['id', 'nombres', 'apellido_paterno', 'apellido_materno']))
            ->limit(12)
            ->get(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'semestre', 'grupo']);

        $projects = Project::query()
            ->with('subjectGroup:id,semestre,grupo')
            ->where('activo', true)
            ->tap(fn ($query) => \App\Support\TextSearch::apply($query, $term, ['titulo']))
            ->limit(12)
            ->get(['id', 'titulo', 'grupo_id']);

        return response()->json(['students' => $students, 'projects' => $projects]);
    }

    public function storeException(Request $request)
    {
        $request->merge(['type' => $request->input('type', 'presentacion_semestre')]);
        $data = $request->validate([
            'period_id' => 'required|integer|exists:periodos_academicos,id',
            'project_id' => 'nullable|integer', 'student_id' => 'nullable|string',
            'course_id' => 'nullable|integer', 'evaluation_id' => 'nullable|integer',
            'type' => 'required|in:presentacion_semestre,reprogramacion,reintento,inscripcion_semestre',
            'presentation_semester' => 'nullable|required_if:type,presentacion_semestre|integer|in:5,6,7,8',
            'reason' => 'required|string|max:1000', 'expires_at' => 'required|date|after:now',
        ]);
        return response()->json(['message' => 'Solicitud registrada; requiere resolución',
            'exception' => app(\App\Services\AcademicAuthorizationService::class)->request($data)], 201);
    }

    public function decideException(Request $request, int $exception)
    {
        $data = $request->validate(['version' => 'required|integer|min:1', 'status' => 'required|in:aprobada,rechazada,revocada',
            'reason' => 'required|string|max:1000', 'project_scope' => 'sometimes|boolean']);
        return response()->json(['message' => 'Resolución registrada',
            'exception' => app(\App\Services\AcademicAuthorizationService::class)->decide($exception, $data)]);
    }

    public function destroyException(Request $request, int $exception)
    {
        $request->merge(['status' => 'revocada']);
        return $this->decideException($request, $exception);
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
        ];
    }
}
