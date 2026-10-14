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
        // Scheduler prepares work only; it must never activate or promote students.
        return app(\App\Support\CareerContext::class)->careerId() ? $this->activePeriod() : null;
    }

    public function activate(AcademicPeriod $period, bool $allowAutomaticPromotion = false): AcademicPeriod
    {
        app(AcademicPeriodLifecycleService::class)->transition($period, 'abierto');
        return $period->fresh();
    }

    public function semestersForPeriod(AcademicPeriod $period): array
    {
        return [5, 6, 7, 8, 9];
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
