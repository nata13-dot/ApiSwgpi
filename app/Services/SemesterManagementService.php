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
        $configured = (string) SystemSetting::valueFor('active_academic_period', '');
        if ($configured !== '') {
            $period = AcademicPeriod::where('nombre', $configured)->first();
            if ($period) {
                return $period;
            }
        }

        return AcademicPeriod::query()
            ->where('activo', true)
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    public function syncCurrentPeriod(): ?AcademicPeriod
    {
        $period = AcademicPeriod::query()
            ->whereDate('fecha_inicio', '<=', today())
            ->whereDate('fecha_fin', '>=', today())
            ->orderByDesc('fecha_inicio')
            ->first();

        if (!$period) {
            return $this->activePeriod();
        }

        return $this->activate($period, true);
    }

    public function activate(AcademicPeriod $period, bool $allowAutomaticPromotion = false): AcademicPeriod
    {
        return DB::transaction(function () use ($period, $allowAutomaticPromotion) {
            AcademicPeriod::where('id', '<>', $period->id)->update(['activo' => false]);
            $period->update(['activo' => true]);
            SystemSetting::setValue('active_academic_period', $period->nombre, 'string');

            if ($allowAutomaticPromotion && $period->promocion_automatica && !$period->promocion_aplicada_en) {
                $this->promoteStudents($period);
                $period->update(['promocion_aplicada_en' => now()]);
            }

            return $period->fresh();
        });
    }

    public function promoteStudents(AcademicPeriod $destination): array
    {
        $destinationSemesters = $this->semestersForPeriod($destination);
        $students = User::query()
            ->where('perfil_id', 3)
            ->where('activo', true)
            ->whereBetween('semestre', [5, 9])
            ->get(['id', 'semestre']);
        $updated = 0;

        foreach ($students as $student) {
            $nextSemester = (int) $student->semestre + 1;
            if ($nextSemester > 9 || !in_array($nextSemester, $destinationSemesters, true)) {
                continue;
            }
            $student->update(['semestre' => $nextSemester]);
            $updated++;
        }

        return ['reviewed' => $students->count(), 'updated' => $updated];
    }

    public function semestersForPeriod(AcademicPeriod $period): array
    {
        if (preg_match('/-(1|2)$/', $period->nombre, $matches)) {
            return $matches[1] === '1' ? [6, 8] : [5, 7, 9];
        }

        return [5, 6, 7, 8, 9];
    }

    public function presentationSemester(int $projectId, ?int $academicSemester = null, ?AcademicPeriod $period = null): ?int
    {
        $period ??= $this->activePeriod();
        if (!$period || !Schema::hasTable('autorizaciones_excepcionales')) {
            return $academicSemester;
        }

        $projectSemester = SemesterPresentationException::query()
            ->where('proyecto_id', $projectId)
            ->whereNull('usuario_id')
            ->where('tipo', 'presentacion_semestre')
            ->where('activa', true)
            ->orderByDesc('id')
            ->get()
            ->first(fn ($exception) => (int) $exception->periodo_id === (int) $period->id)
            ?->semestre_presentacion;

        if ($projectSemester) {
            return (int) $projectSemester;
        }

        $studentSemester = SemesterPresentationException::query()
            ->where('tipo', 'presentacion_semestre')
            ->where('activa', true)
            ->whereIn('usuario_id', DB::table('proyecto_integrantes')
                ->where('proyecto_id', $projectId)
                ->whereIn('rol', ['lider', 'integrante'])
                ->select('usuario_id'))
            ->orderByDesc('id')
            ->get()
            ->first(fn ($exception) => (int) $exception->periodo_id === (int) $period->id)
            ?->semestre_presentacion;

        return $studentSemester ? (int) $studentSemester : $academicSemester;
    }

    public function presentationSemestersForProjects(Collection $projects, ?AcademicPeriod $period = null): array
    {
        $semesters = $projects->mapWithKeys(
            fn ($project) => [(int) $project->id => $project->subjectGroup?->semestre]
        )->all();
        $period ??= $this->activePeriod();

        if (!$period || !$projects->count() || !Schema::hasTable('autorizaciones_excepcionales')) {
            return $semesters;
        }

        $projectIds = $projects->pluck('id')->map(fn ($id) => (int) $id)->all();
        $projectExceptions = SemesterPresentationException::query()
            ->where('tipo', 'presentacion_semestre')
            ->where('activa', true)
            ->whereNull('usuario_id')
            ->whereIn('proyecto_id', $projectIds)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($exception) => (int) $exception->periodo_id === (int) $period->id)
            ->keyBy('proyecto_id');

        $studentExceptions = SemesterPresentationException::query()
            ->where('tipo', 'presentacion_semestre')
            ->where('activa', true)
            ->whereNotNull('usuario_id')
            ->whereIn('proyecto_id', $projectIds)
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($exception) => (int) $exception->periodo_id === (int) $period->id)
            ->keyBy('proyecto_id');

        foreach ($projectIds as $projectId) {
            $exception = $projectExceptions->get($projectId) ?? $studentExceptions->get($projectId);
            if ($exception) {
                $semesters[$projectId] = (int) $exception->semestre_presentacion;
            }
        }

        return $semesters;
    }
}
