<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\AcademicPeriodAutomationService;
use App\Services\AcademicWorkflowService;
use App\Support\CareerScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Operación del periodo: las etapas de la carrera en orden (periodo, autoregistro y solicitudes, propuestas,
 * evaluaciones y cierre) con su estado y sus pendientes. Los controles siguen en sus rutas de siempre.
 */
class PeriodCenterController extends Controller
{
    public function __invoke(AcademicWorkflowService $workflow, AcademicPeriodAutomationService $automation)
    {
        $user = auth('api')->user();
        $career = (int) $workflow->careerId();
        $period = $workflow->period($career);
        $state = $period ? DB::table('carrera_periodos')->where(['carrera_id' => $career, 'periodo_id' => $period->id])->first() : null;
        $academic = $user->canManageAcademics() || $user->isGeneralAdmin();
        $plan = $academic && !$period ? $automation->plan($career) : null;

        // Mismos datos que la pantalla de propuestas, para no duplicar reglas.
        $settings = json_decode(app(ProposalWorkflowController::class)->settings()->getContent(), true);
        $self = $settings['self_registration'];

        $rooms = $period ? CareerScope::constrain(DB::table('salas_evaluacion'), 'salas_evaluacion', $career)->where('salas_evaluacion.periodo_id', $period->id) : null;
        $end = $period ? Carbon::parse($period->fecha_fin)->endOfDay() : null;

        return response()->json([
            'career' => app(\App\Support\CareerContext::class)->career()?->only(['id', 'nombre', 'nombre_corto']),
            'permissions' => ['academic' => $academic, 'self_registration' => (bool) $self['can_manage'], 'proposals' => $user->canManageProjects() || $user->isGeneralAdmin()],
            'period' => [
                'open' => (bool) $period,
                'name' => $period?->nombre ?? $plan['period']['nombre'] ?? null,
                'starts' => $period ? Carbon::parse($period->fecha_inicio)->toDateString() : null,
                'ends' => $end?->toDateString(),
                'state' => $state?->estado ?? $plan['state'] ?? null,
                'reconciled' => (bool) ($state->conciliado ?? false),
                'unplaced' => $academic && $period ? count($automation->unplaced($career, (int) $period->id)) : null,
                'can_open' => (bool) ($plan['can_apply'] ?? false),
                'to_promote' => $plan ? count($plan['moves']) : null,
            ],
            'self_registration' => [
                'open' => $self['open'], 'until' => $self['until'], 'semester' => $self['semester'], 'semester_options' => $self['semester_options'],
                'groups' => $self['groups'], 'blocker' => $self['blocker'], 'rule' => $self['rule'],
                'pending_requests' => DB::table('autoregistros_pendientes')->where('carrera_id', $career)->where('estado', 'por_aprobar')->count(),
            ],
            'proposals' => [
                'open' => (bool) ($settings['proposal_window']['open'] ?? false),
                'until' => $settings['proposal_window']['until'] ?? null,
                'pending' => Project::where('tipo', 'propuesta')->where('activo', true)->where('estado', 'pendiente')->count(),
                'changes' => Project::where('tipo', 'propuesta')->where('activo', true)->where('estado', 'requiere_cambios')->count(),
                'validators' => count($settings['validator_ids'] ?? []),
            ],
            'evaluations' => [
                'scheduled' => $rooms ? (clone $rooms)->where('estado', 'programada')->count() : 0,
                'finished' => $rooms ? (clone $rooms)->where('estado', 'finalizada')->count() : 0,
                'next_at' => $rooms ? (clone $rooms)->where('estado', 'programada')->where('inicia_en', '>=', now())->min('inicia_en') : null,
            ],
            'closing' => [
                'ends' => $end?->toDateString(),
                'days_left' => $end ? max(0, (int) now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false)) : null,
                'pending' => $period ? $automation->pendingAtClose($career, (int) $period->id) : [],
            ],
        ]);
    }

    /** Contadores del menú Gestiones; ligeros porque se piden en cada página. */
    public function badges(AcademicWorkflowService $workflow)
    {
        $user = auth('api')->user();
        $career = (int) $workflow->careerId();
        $academic = $user->canManageAcademics() || $user->isGeneralAdmin();

        return response()->json([
            'registration_requests' => $academic ? DB::table('autoregistros_pendientes')->where('carrera_id', $career)->where('estado', 'por_aprobar')->count() : 0,
            'pending_proposals' => Project::where('tipo', 'propuesta')->where('activo', true)->where('estado', 'pendiente')->count(),
        ]);
    }
}
