<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Project;
use App\Models\RepositoryDocument;
use Illuminate\Support\Facades\DB;

/**
 * Requisitos para presentar en sala, según la etapa que se evalúa:
 *   5° propuesta: hoja de liberación y presentación (PPTX).
 *   6° y 7° avance: además enlace de Drive y documento de reporte en avance.
 *   8° titulación: además enlace de Drive y documento de reporte final.
 * Un proyecto se evalúa cuando tiene todo lo que le aplica, cada entrega aprobada por un encargado y al
 * menos un integrante liberado; el resultado sólo cuenta para los liberados. Sólo cuentan las entregas del
 * periodo evaluado. Revisan y liberan el administrador general, jefatura, asistencia y coordinación
 * (ésta, no en tesis); los docentes sólo consultan.
 */
final class EvaluationRequirementsService
{
    public const PRESENTATION_MAX_MB = 100;
    public const DRIVE_HOSTS = ['drive.google.com', 'docs.google.com'];

    private array $cache = [];

    public static function requirements(int $semester): array
    {
        return [
            'release_sheet' => true,
            'presentation' => true,
            'link' => $semester >= 6,
            'report' => $semester >= 8 ? 'final' : ($semester >= 6 ? 'avance' : null),
        ];
    }

    public const LABELS = ['presentation' => 'presentación', 'link' => 'enlace de Drive', 'report' => 'documento de reporte'];

    /** Quién revisa entregas y libera alumnos del proyecto. */
    public static function canReview($user, ?Project $project = null): bool
    {
        if (!$user) return false;
        if ($user->isGeneralAdmin()) return true;
        $profile = (int) app(\App\Support\CareerContext::class)->profileId();
        if (!in_array($profile, [1, 5, 6, 7], true)) return false;

        return !($profile === 7 && $project && $project->tipo === 'tesis');
    }

    public static function isDriveLink(string $url): bool
    {
        $parts = parse_url(trim($url));

        return ($parts['scheme'] ?? '') === 'https' && in_array(strtolower($parts['host'] ?? ''), self::DRIVE_HOSTS, true);
    }

    public function status(Project $project, int $semester, ?AcademicPeriod $period): array
    {
        $key = $project->id . ':' . $semester . ':' . ($period?->id ?? 0);
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $requirements = self::requirements($semester);
        $project->loadMissing('students:id,nombres,apa,ama');
        $documents = RepositoryDocument::with('releaseStatuses')
            ->where('project_id', $project->id)
            ->where('activo', true)
            ->whereIn('document_category', [
                RepositoryDocument::CATEGORY_EVALUATION_RELEASE,
                RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION,
                RepositoryDocument::CATEGORY_EVALUATION_REPORT,
            ])
            ->when($period?->fecha_inicio && $period?->fecha_fin, fn ($query) => $query->whereBetween('creado_en', [$period->fecha_inicio, $period->fecha_fin->copy()->endOfDay()]))
            ->orderByDesc('created_at')
            ->get();
        $releaseSheet = $documents->firstWhere('document_category', RepositoryDocument::CATEGORY_EVALUATION_RELEASE);
        $presentation = $documents->firstWhere('document_category', RepositoryDocument::CATEGORY_EVALUATION_PRESENTATION);
        $report = $documents->firstWhere('document_category', RepositoryDocument::CATEGORY_EVALUATION_REPORT);
        $link = $period ? DB::table('enlaces_evaluacion')->where('proyecto_id', $project->id)->where('periodo_id', $period->id)->value('url') : null;
        $reviews = $period ? DB::table('revisiones_entregas_evaluacion as r')->leftJoin('usuarios as u', 'u.id', '=', 'r.revisado_por')
            ->where('r.proyecto_id', $project->id)->where('r.periodo_id', $period->id)
            ->get(['r.entrega', 'r.estado', 'r.comentario', 'r.revisado_en', 'u.nombres as revisado_por'])->keyBy('entrega') : collect();

        $statuses = $releaseSheet?->releaseStatuses?->keyBy(fn ($status) => (string) $status->alumno_id) ?? collect();
        // Presentaciones negadas por decisión administrativa (PresentationDenialService): el alumno negado no cuenta como liberado.
        $denials = app(PresentationDenialService::class)->active($project->id, $period?->id);
        $students = $project->students->map(function ($student) use ($statuses, $denials) {
            $status = $statuses->get((string) $student->id);
            $denied = $denials['students'][(string) $student->id] ?? null;

            return [
                'id' => $student->id,
                'name' => trim("{$student->nombres} {$student->apa} {$student->ama}"),
                'released' => !$denied && (bool) ($status?->liberado ?? false),
                'reviewed' => $status?->revisado_en !== null,
                'denial' => $denied,
            ];
        })->values();
        $releasedCount = $students->where('released', true)->count();

        // Estado de cada entrega: faltante, pendiente de revisión, con observaciones o aprobada.
        $present = ['presentation' => $presentation !== null, 'link' => $link !== null, 'report' => $report !== null];
        $applies = ['presentation' => true, 'link' => $requirements['link'], 'report' => (bool) $requirements['report']];
        $deliveries = [];
        $missing = [];
        if ($denials['team']) $missing[] = 'que se permita la presentación (negada por decisión administrativa)';
        if (!$releaseSheet) $missing[] = 'hoja de liberación';
        foreach ($present as $type => $exists) {
            if (!$applies[$type]) continue;
            $review = $reviews->get($type);
            $state = !$exists ? 'faltante' : ($review->estado ?? 'pendiente');
            $deliveries[$type] = [
                'state' => $state,
                'comment' => $exists ? ($review->comentario ?? null) : null,
                'reviewed_by' => $exists ? ($review->revisado_por ?? null) : null,
                'reviewed_at' => $exists ? ($review->revisado_en ?? null) : null,
            ];
            $label = self::LABELS[$type] . ($type === 'report' ? " ({$requirements['report']})" : '');
            if ($state === 'faltante') $missing[] = $label;
            elseif ($state === 'pendiente') $missing[] = "{$label} pendiente de revisión";
            elseif ($state === 'con_observaciones') $missing[] = "{$label} con observaciones";
        }
        if ($releaseSheet && $releasedCount === 0) $missing[] = 'al menos un alumno liberado';

        return $this->cache[$key] = [
            'requirements' => $requirements,
            'release_sheet_uploaded' => $releaseSheet !== null,
            'presentation_uploaded' => $presentation !== null,
            'link_registered' => $link !== null,
            'link' => $link,
            'report_uploaded' => $report !== null,
            'all_students_released' => $students->isNotEmpty() && $releasedCount === $students->count(),
            'released_count' => $releasedCount,
            'deliveries' => $deliveries,
            'students' => $students,
            'missing' => $missing,
            'denial' => $denials['team'],
            'ready' => $missing === [],
        ];
    }

    /** Al reemplazar una entrega su revisión anterior deja de valer. */
    public static function resetReview(int $projectId, int $periodId, string $type): void
    {
        DB::table('revisiones_entregas_evaluacion')->where(['proyecto_id' => $projectId, 'periodo_id' => $periodId, 'entrega' => $type])->delete();
    }

    /** Mensaje para quien intenta evaluar un proyecto incompleto. */
    public static function blockMessage(array $status): string
    {
        return 'No se puede evaluar todavía: falta ' . implode(', ', $status['missing']) . '.';
    }
}
