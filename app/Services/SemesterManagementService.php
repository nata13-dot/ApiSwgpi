<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\SemesterPresentationException;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class SemesterManagementService
{
    public function activePeriod(): ?AcademicPeriod
    {
        return app(AcademicWorkflowService::class)->period();
    }

    public function syncCurrentPeriod(): ?AcademicPeriod
    {
        // La apertura por fecha la hace AcademicPeriodAutomationService; esta consulta no cambia nada.
        return app(\App\Support\CareerContext::class)->careerId() ? $this->activePeriod() : null;
    }

    public function activate(AcademicPeriod $period, bool $allowAutomaticPromotion = false): AcademicPeriod
    {
        app(AcademicPeriodLifecycleService::class)->transition($period, 'abierto');
        return $period->fresh();
    }

    /** El administrador general habilita 1°–4° en carreras con casos integradores; por defecto sólo 5°–9°. */
    public function basicSemestersEnabled(?int $careerId = null): bool
    {
        $careerId ??= app(\App\Support\CareerContext::class)->careerId();

        return (bool) \App\Models\CareerSetting::valueForCareer($careerId, 'basic_semesters_enabled', false);
    }

    public function careerSemesters(?int $careerId = null): array
    {
        return $this->basicSemestersEnabled($careerId) ? range(1, 9) : range(5, 9);
    }

    /** 1 = enero–junio (semestres pares), 2 = julio–diciembre (semestres impares). */
    public function halfOfYear(AcademicPeriod $period): int
    {
        if ($period->fecha_inicio) {
            return $period->fecha_inicio->month <= 6 ? 1 : 2;
        }

        // Periodos sin fechas: el código institucional termina en -1 (enero–junio) o -2 (julio–diciembre).
        return preg_match('/-1$/', (string) $period->nombre) || preg_match('/^enero/i', (string) $period->nombre) ? 1 : 2;
    }

    public function semestersForPeriod(AcademicPeriod $period, ?int $careerId = null): array
    {
        $parity = $this->halfOfYear($period) === 1 ? 0 : 1;

        return array_values(array_filter($this->careerSemesters($careerId), fn ($semester) => $semester % 2 === $parity));
    }

    public function assertSemesterInPeriod(int $semester, AcademicPeriod $period, string $field = 'semestre'): void
    {
        $allowed = $this->semestersForPeriod($period);
        if (!in_array($semester, $allowed, true)) {
            $half = $this->halfOfYear($period) === 1 ? 'enero–junio' : 'julio–diciembre';
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => ["En el periodo {$period->nombre} ({$half}) sólo existen los semestres ".implode(', ', $allowed).'.'],
            ]);
        }
    }

    public function presentationSemester(int $projectId, ?int $academicSemester = null, ?AcademicPeriod $period = null): ?int
    {
        $period ??= $this->activePeriod();
        if (!$period) return $academicSemester;
        $authorization = app(AcademicAuthorizationService::class)->valid('presentacion_semestre', $projectId, $period->id)->first();
        return $authorization ? (int) $authorization->semestre_destino : $academicSemester;
    }

    public function presentationSemestersForProjects(Collection $projects, ?AcademicPeriod $period = null): array
    {
        $period ??= $this->activePeriod();
        return $projects->mapWithKeys(fn ($project) => [$project->id => $this->presentationSemester(
            $project->id, $period ? app(AcademicWorkflowService::class)->projectSemester($project, $period->id) : null, $period
        )])->all();
    }
}
