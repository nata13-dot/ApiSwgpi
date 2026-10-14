<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\Project;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Support\CareerContext;
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
        abort_unless(Schema::hasTable('carrera_periodos'), 503, 'La actualización académica requiere instalar y conciliar el esquema.');
    }

    public function manager(): void
    {
        abort_unless(auth('api')->user()?->isGeneralAdmin() || in_array(app(CareerContext::class)->profileId(), [1, 5], true), 403, 'Se requiere jefatura o administración de carrera.');
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

    public function period(?int $career = null): ?AcademicPeriod
    {
        $this->ready();
        $id = DB::table('carrera_periodos')->where('carrera_id', $career ?? $this->careerId())
            ->whereIn('estado', ['abierto', 'en_cierre'])->value('periodo_id');
        return $id ? AcademicPeriod::find($id) : null;
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
        abort_unless(DB::table('carrera_periodos')->where('carrera_id', $this->careerId())->where('periodo_id', $periodId)
            ->whereIn('estado', ['abierto', 'en_cierre'])->where('conciliado', true)->exists(), 409, 'El periodo no admite revisiones.');
    }

    public function enrollment(string $studentId, ?int $periodId = null): ?object
    {
        $periodId ??= $this->period()?->id;
        if (!$periodId) return null;
        return DB::table('matriculas_academicas as m')->join('grupos_academicos as g', 'g.id', '=', 'm.grupo_id')
            ->where('m.carrera_id', $this->careerId())->where('m.periodo_id', $periodId)
            ->where('m.estudiante_id', $studentId)->where('m.estado', 'activa')
            ->select('m.*', 'g.semestre', 'g.clave_grupo')->first();
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
            $key = ['carrera_id' => $this->careerId(), 'periodo_id' => $group->periodo_id, 'estudiante_id' => $studentId];
            $old = DB::table('matriculas_academicas')->where($key)->first();
            DB::table('matriculas_academicas')->updateOrInsert($key, [
                'grupo_id' => $group->id, 'estado' => 'activa', 'version' => ($old->version ?? 0) + 1,
                'created_at' => $old->created_at ?? now(), 'updated_at' => now(),
            ]);
            $this->audit('matricula.asignada', 'matricula', $studentId, $old, $key + ['grupo_id' => $group->id]);
        });
    }

    public function teacher(string $id): User
    {
        return User::withCareerProfiles([1, 2, 5, 6, 7])->where('activo', true)->findOrFail($id);
    }

    public function projectSemester(Project $project, int $period): ?int
    {
        $group = DB::table('proyecto_periodos')->where('carrera_id', $this->careerId())
            ->where('proyecto_id', $project->id)->where('periodo_id', $period)->value('grupo_id');
        if ($group) return (int) SubjectGroup::findOrFail($group)->semestre;
        return (int) $project->subjectGroup?->periodo_id === $period ? $project->subjectGroup?->semestre : null;
    }
}
