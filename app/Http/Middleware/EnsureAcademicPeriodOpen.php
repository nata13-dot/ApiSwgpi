<?php
namespace App\Http\Middleware;

use App\Services\AcademicWorkflowService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureAcademicPeriodOpen
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe()) return $next($request);
        $path = preg_replace('#^api/#', '', $request->path());
        if (!preg_match('#^(evaluations(?:/|$)|deliverables(?:/|$)|projects(?:/|$)|proposal/(projects|windows|assignments|exceptions)|residencies(?:/|$)|course-enrollments(?:/|$)|repositorio/(evaluation-documents|thesis-documents)|semester-management/exceptions|academic-operations/(authorizations|enrollments|project-periods))#', $path)) return $next($request);
        $workflow = app(AcademicWorkflowService::class);
        $workflow->ready();
        // Serializes close with ordinary writes, including file submissions.
        return DB::transaction(function () use ($request, $next, $workflow, $path) {
            DB::table('carreras')->where('id', $workflow->careerId())->lockForUpdate()->first();
            $state = DB::table('carrera_periodos')->where('carrera_id', $workflow->careerId())->whereIn('estado', ['abierto', 'en_cierre'])->lockForUpdate()->first();
            abort_unless($state && $state->conciliado, 409, 'La carrera no tiene un periodo abierto y conciliado.');
            $finishing = preg_match('#^(residencies/\d+/(close|versions/\d+/review)|repositorio/evaluation-documents/\d+/release-status|deliverables/\d+/calificar|evaluations/(\d+/(score|mark-completed|feedback)|rooms/\d+/(advance|timer|lock-sequence|archive)))$#', $path);
            $finishing = $finishing || (str_starts_with($path, 'semester-management/exceptions/') && ($request->isMethod('DELETE') || in_array($request->input('status'), ['rechazada', 'revocada'], true)));
            abort_if($state->estado === 'en_cierre' && !$finishing, 409, 'El periodo está en cierre: sólo admite revisiones y finalización de evaluaciones.');
            if (!$finishing) $workflow->requireOpen((int) $state->periodo_id);
            $id = $request->route('id');
            if (str_starts_with($path, 'repositorio/evaluation-documents')) {
                $period = \App\Models\AcademicPeriod::findOrFail($state->periodo_id);
                if ($id) {
                    $document = \App\Models\RepositoryDocument::findOrFail($id);
                    abort_unless($document->created_at && $document->created_at->betweenIncluded($period->fecha_inicio, $period->fecha_fin->endOfDay()), 409, 'Documento de otro periodo; se conserva su revisión histórica.');
                } else {
                    $project = \App\Models\Project::findOrFail($request->input('project_id'));
                    abort_unless($workflow->projectSemester($project, $state->periodo_id), 409, 'Registra la continuidad del proyecto antes de entregar.');
                }
            }
            if ($id && preg_match('#^evaluations/rooms/#', $path)) {
                $room = \App\Models\EvaluationRoom::findOrFail($id);
                abort_unless((int) $room->periodo_id === (int) $state->periodo_id, 409, 'Sala de otro periodo; conserva su historial.');
            } elseif ($id && preg_match('#^evaluations/\d+#', $path)) {
                $evaluation = \App\Models\Evaluation::findOrFail($id);
                abort_unless((int) $evaluation->room?->periodo_id === (int) $state->periodo_id, 409, 'Evaluación de otro periodo.');
            } elseif ($id && preg_match('#^deliverables/#', $path)) {
                $deliverable = \App\Models\Deliverable::findOrFail($id);
                abort_unless((int) $deliverable->course?->group?->periodo_id === (int) $state->periodo_id, 409, 'Entregable de otro periodo.');
            }
            return $next($request);
        }, 1);
    }
}
