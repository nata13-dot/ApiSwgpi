<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Project;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Support\CareerContext;
use App\Support\CareerScope;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AcademicWorkflowService
{
    public function careerId(): int
    {
        $id = app(CareerContext::class)->careerId();
        abort_unless($id, 403, 'Selecciona una carrera.');
        return $id;
    }

    public function ready(): void
    {
        abort_unless(\App\Support\SchemaCache::hasTable('carrera_periodos'), 503, 'La actualización académica requiere instalar y conciliar el esquema.');
    }

    public function manager(): void
    {
        abort_unless(auth('api')->user()?->isGeneralAdmin() || in_array(app(CareerContext::class)->profileId(), [1, 5], true), 403, 'Se requiere jefatura o administración de carrera.');
    }

    /**
     * Aprueban autorizaciones y casos especiales: jefe, asistente de jefatura y coordinador de proyectos
     * integradores (este último, sólo para proyectos que no son tesis).
     */
    public function approver(?\App\Models\Project $project = null): void
    {
        if (auth('api')->user()?->isGeneralAdmin()) return;
        $profile = app(CareerContext::class)->profileId();
        abort_unless(in_array($profile, [1, 5, 6, 7], true), 403, 'Se requiere jefatura, asistencia de jefatura o coordinación de proyectos.');
        abort_if($profile === \App\Support\Profiles::PROJECT_COORDINATOR && $project && $project->tipo === 'tesis', 403, 'Las tesis las autoriza la jefatura.');
    }

    public function audit(string $action, string $entity, string|int $id, mixed $before, mixed $after): void
    {
        DB::table('bitacora_academica')->insert([
            'carrera_id' => app(CareerContext::class)->careerId(), 'actor_id' => auth('api')->id(),
            'accion' => $action, 'entidad' => $entity, 'entidad_id' => (string) $id,
            'antes' => $before === null ? null : json_encode($before),
            'despues' => $after === null ? null : json_encode($after), 'creado_en' => now(),
        ]);
    }

    // Se consulta por cada fila serializada; durante la petición sólo cambia vía forgetPeriods().
    public function period(?int $career = null): ?AcademicPeriod
    {
        $this->ready();
        $career ??= $this->careerId();

        return RequestMemo::remember("periodo-abierto:{$career}", function () use ($career) {
            $id = DB::table('carrera_periodos')->where('carrera_id', $career)
                ->whereIn('estado', ['abierto', 'en_cierre'])->value('periodo_id');

            return $id ? AcademicPeriod::find($id) : null;
        });
    }

    public static function forgetPeriods(): void
    {
        RequestMemo::forget('periodo-abierto:');
    }

    public function requireOpen(int $periodId): void
    {
        $this->ready();
        $period = AcademicPeriod::find($periodId);
        abort_unless($period && today()->betweenIncluded($period->fecha_inicio, $period->fecha_fin), 409, 'El periodo está fuera de las fechas institucionales.');
        abort_unless(DB::table('carrera_periodos')->where('carrera_id', $this->careerId())
            ->where('periodo_id', $periodId)->where('estado', 'abierto')->where('conciliado', true)->exists(), 409,
            'El periodo de la carrera no está abierto y conciliado.');
    }

    public function requireReviewable(int $periodId): void
    {
        $this->ready();
        $state = DB::table('carrera_periodos as cp')->join('periodos_academicos as p', 'p.id', '=', 'cp.periodo_id')
            ->where('cp.carrera_id', $this->careerId())->where('cp.periodo_id', $periodId)->where('cp.conciliado', true)->first(['cp.estado', 'p.fecha_fin']);
        // Los periodos concluyen por fecha aunque quede algo pendiente: hay 60 días para terminar de revisarlo.
        $reviewable = $state && (in_array($state->estado, ['abierto', 'en_cierre'], true)
            || ($state->estado === 'cerrado' && today()->lte(\Illuminate\Support\Carbon::parse($state->fecha_fin)->addDays(60))));
        abort_unless($reviewable, 409, 'El periodo no admite revisiones.');
    }

    public function enrollment(string $studentId, ?int $periodId = null): ?object
    {
        $periodId ??= $this->period()?->id;
        if (!$periodId) return null;
        return CareerScope::enrollments($this->careerId(), $periodId)
            ->where('m.estudiante_id', $studentId)->where('m.estado', 'activa')
            ->addSelect('m_grupo.semestre', 'm_grupo.clave_grupo')->first();
    }

    public function assign(string $studentId, SubjectGroup $group): void
    {
        $this->requireOpen((int) $group->periodo_id);
        abort_unless((int) $group->carrera_id === $this->careerId() && $group->activo, 422, 'Grupo no disponible.');
        User::students()->where('activo', true)->findOrFail($studentId);
        DB::transaction(function () use ($studentId, $group) {
            DB::table('carreras')->where('id', $this->careerId())->lockForUpdate()->first();
            $this->requireOpen((int) $group->periodo_id);
            // A stable parent lock serializes initial inserts as well as later edits.
            DB::table('usuarios')->where('id', $studentId)->lockForUpdate()->first();
            // Carrera y periodo de la matrícula son los de su grupo.
            $key = ['carrera_id' => $this->careerId(), 'periodo_id' => $group->periodo_id, 'estudiante_id' => $studentId];
            $old = CareerScope::enrollments($this->careerId(), (int) $group->periodo_id)->where('m.estudiante_id', $studentId)->first();
            $values = ['grupo_id' => $group->id, 'estado' => 'activa', 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            $old ? DB::table('matriculas_academicas')->where('id', $old->id)->update($values)
                : DB::table('matriculas_academicas')->insert($values + ['estudiante_id' => $studentId, 'created_at' => now()]);
            $this->audit('matricula.asignada', 'matricula', $studentId, $old, $key + ['grupo_id' => $group->id]);
        });
    }

    public function teacher(string $id): User
    {
        return User::withCareerProfiles([1, 2, 5, 6, 7])->where('activo', true)->findOrFail($id);
    }

    public function projectSemester(Project $project, int $period): ?int
    {
        $group = DB::table('proyecto_periodos as pp')->join('grupos_academicos as g', 'g.id', '=', 'pp.grupo_id')
            ->where('pp.proyecto_id', $project->id)->where('g.periodo_id', $period)->value('pp.grupo_id');
        if ($group) return (int) SubjectGroup::findOrFail($group)->semestre;
        return (int) $project->subjectGroup?->periodo_id === $period ? $project->subjectGroup?->semestre : null;
    }
}
