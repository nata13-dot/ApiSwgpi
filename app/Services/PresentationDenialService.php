<?php

namespace App\Services;

use App\Mail\PresentationDenialMail;
use App\Models\EvaluationDocumentRelease;
use App\Models\Project;
use App\Models\RepositoryDocument;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Negar la presentación de un equipo o de un alumno en el periodo, por decisión administrativa. El equipo negado no
 * puede ponerse en sala ni evaluarse; el alumno negado no puede liberarse ni presentar, pero su equipo sí.
 * Se avisa en la app y por correo, y se puede permitir de nuevo (queda el historial).
 */
class PresentationDenialService
{
    public const DEFAULT_REASON = 'Se te impide continuar por una decisión administrativa.';

    /** Negaciones vigentes del proyecto en el periodo: ['team' => ?array, 'students' => [alumno_id => array]]. */
    public function active(int $projectId, ?int $periodId): array
    {
        if (!$periodId || !SchemaCache::hasTable('negaciones_presentacion')) {
            return ['team' => null, 'students' => []];
        }
        $rows = DB::table('negaciones_presentacion as n')->leftJoin('usuarios as u', 'u.id', '=', 'n.negado_por')
            ->where('n.proyecto_id', $projectId)->where('n.periodo_id', $periodId)->whereNull('n.retirado_en')
            ->get(['n.id', 'n.alumno_id', 'n.motivo', 'n.negado_en', 'u.nombres as negado_por'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'student_id' => $row->alumno_id, 'reason' => $row->motivo ?: self::DEFAULT_REASON,
                'custom_reason' => $row->motivo !== null, 'denied_by' => $row->negado_por, 'denied_at' => $row->negado_en]);

        return [
            'team' => $rows->firstWhere('student_id', null),
            'students' => $rows->whereNotNull('student_id')->keyBy(fn ($row) => (string) $row['student_id'])->all(),
        ];
    }

    public function deny(Project $project, int $periodId, ?string $studentId, ?string $reason, User $actor): int
    {
        $reason = trim((string) $reason) ?: null;
        $id = DB::transaction(function () use ($project, $periodId, $studentId, $reason, $actor) {
            $exists = DB::table('negaciones_presentacion')->where(['proyecto_id' => $project->id, 'periodo_id' => $periodId, 'alumno_id' => $studentId])
                ->whereNull('retirado_en')->lockForUpdate()->exists();
            abort_if($exists, 409, $studentId ? 'Ese alumno ya tiene la presentación negada.' : 'El equipo ya tiene la presentación negada.');
            if ($studentId) {
                // Un alumno negado deja de estar liberado en la hoja de este periodo.
                EvaluationDocumentRelease::where('alumno_id', $studentId)
                    ->whereIn('documento_repositorio_id', RepositoryDocument::where('project_id', $project->id)->pluck('id'))
                    ->update(['liberado' => false, 'revisado_por' => $actor->id, 'revisado_en' => now()]);
            }

            return DB::table('negaciones_presentacion')->insertGetId(['proyecto_id' => $project->id, 'periodo_id' => $periodId, 'alumno_id' => $studentId,
                'motivo' => $reason, 'negado_por' => $actor->id, 'negado_en' => now()]);
        });
        app(AcademicWorkflowService::class)->audit('evaluacion.presentacion_negada', 'proyecto', $project->id, null, ['alumno_id' => $studentId, 'motivo' => $reason]);
        $this->notify($project, $studentId, true, $reason ?: self::DEFAULT_REASON, $actor);

        return $id;
    }

    public function allow(Project $project, int $denialId, User $actor): void
    {
        $denial = DB::table('negaciones_presentacion')->where('id', $denialId)->where('proyecto_id', $project->id)->whereNull('retirado_en')->first();
        abort_unless($denial, 404, 'La negación ya fue retirada.');
        DB::table('negaciones_presentacion')->where('id', $denialId)->update(['retirado_por' => $actor->id, 'retirado_en' => now()]);
        app(AcademicWorkflowService::class)->audit('evaluacion.presentacion_permitida', 'proyecto', $project->id, ['alumno_id' => $denial->alumno_id], null);
        $this->notify($project, $denial->alumno_id, false, null, $actor);
    }

    private function notify(Project $project, ?string $studentId, bool $denied, ?string $reason, User $actor): void
    {
        $project->loadMissing('students:id,nombres,correo');
        /** @var Collection $students */
        $students = $studentId ? $project->students->where('id', $studentId) : $project->students;
        $scope = $studentId ? 'tu presentación' : "la presentación de tu equipo en «{$project->titulo}»";
        $title = $denied ? 'Presentación negada' : 'Presentación permitida';
        $message = $denied ? ucfirst($scope) . " fue negada. Motivo: {$reason}" : ucfirst($scope) . ' vuelve a estar permitida.';
        ActivityNotificationService::send($students->pluck('id')->map(fn ($id) => (string) $id), (string) $actor->id, 'presentacion_negada', $title, $message, '/evaluation-documents');
        foreach ($students as $student) {
            if (!$student->correo) continue;
            try {
                Mail::to($student->correo)->send(new PresentationDenialMail($student->nombres, $project->titulo, $denied, $reason, $studentId === null));
            } catch (\Throwable $e) {
                logger()->error('No se pudo avisar la negación de presentación.', ['mail_mailer' => config('mail.default'), 'error' => $e->getMessage()]);
            }
        }
    }
}
