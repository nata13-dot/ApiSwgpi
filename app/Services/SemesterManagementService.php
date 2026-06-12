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
        if (!$period || !Schema::hasTable('excepciones_presentacion_semestre')) {
            return $academicSemester;
        }

        $projectSemester = SemesterPresentationException::query()
            ->where('periodo_id', $period->id)
            ->where('proyecto_id', $projectId)
            ->where('activo', true)
            ->value('semestre_presentacion');

        if ($projectSemester) {
            return (int) $projectSemester;
        }

        $studentSemester = SemesterPresentationException::query()
            ->where('periodo_id', $period->id)
            ->where('activo', true)
            ->whereIn('usuario_id', DB::table('proyectos_integrantes')
                ->where('proyecto_id', $projectId)
                ->where('rol', 'integrante')
                ->select('usuario_id'))
            ->latest('actualizado_en')
            ->value('semestre_presentacion');

        return $studentSemester ? (int) $studentSemester : $academicSemester;
    }

    public function presentationSemestersForProjects(Collection $projects, ?AcademicPeriod $period = null): array
    {
        $semesters = $projects->mapWithKeys(
            fn ($project) => [(int) $project->id => $project->subjectGroup?->semestre]
        )->all();
        $period ??= $this->activePeriod();

        if (!$period || !$projects->count() || !Schema::hasTable('excepciones_presentacion_semestre')) {
            return $semesters;
        }

        $projectIds = $projects->pluck('id')->map(fn ($id) => (int) $id)->all();
        $projectExceptions = SemesterPresentationException::query()
            ->where('periodo_id', $period->id)
            ->where('activo', true)
            ->whereIn('proyecto_id', $projectIds)
            ->get(['proyecto_id', 'semestre_presentacion'])
            ->keyBy('proyecto_id');

        $studentExceptions = DB::table('excepciones_presentacion_semestre as excepcion')
            ->join('proyectos_integrantes as integrante', 'integrante.usuario_id', '=', 'excepcion.usuario_id')
            ->where('excepcion.periodo_id', $period->id)
            ->where('excepcion.activo', true)
            ->where('integrante.rol', 'integrante')
            ->whereIn('integrante.proyecto_id', $projectIds)
            ->orderByDesc('excepcion.actualizado_en')
            ->orderByDesc('excepcion.id')
            ->get([
                'integrante.proyecto_id',
                'excepcion.semestre_presentacion',
            ])
            ->groupBy('proyecto_id')
            ->map(fn ($items) => $items->first());

        foreach ($projectIds as $projectId) {
            $exception = $projectExceptions->get($projectId) ?? $studentExceptions->get($projectId);
            if ($exception) {
                $semesters[$projectId] = (int) $exception->semestre_presentacion;
            }
        }

        return $semesters;
    }
}
