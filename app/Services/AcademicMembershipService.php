<?php
namespace App\Services;

use App\Models\UserCareer;
use Illuminate\Support\Facades\DB;

class AcademicMembershipService
{
    public function commitments(string $user, int $career): array
    {
        return [
            'courses' => DB::table('curso_docentes as cd')->join('cursos as c', 'c.id', '=', 'cd.curso_id')
                ->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')->where('g.carrera_id', $career)->where('cd.docente_id', $user)->where('cd.activo', true)->where('c.activo', true)->pluck('c.id')->all(),
            'rooms' => DB::table('sala_docentes as sd')->join('salas_evaluacion as s', 's.id', '=', 'sd.sala_id')
                ->join('rubricas as r', 'r.id', '=', 's.rubrica_id')->where('r.carrera_id', $career)->where('sd.docente_id', $user)->where('s.estado', '!=', 'finalizada')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->pluck('s.id')->all(),
            'projects' => DB::table('proyecto_integrantes as pi')->join('proyectos as p', 'p.id', '=', 'pi.proyecto_id')
                ->join('grupos_academicos as g', 'g.id', '=', 'p.grupo_id')->where('g.carrera_id', $career)->where('pi.usuario_id', $user)->whereNotIn('pi.rol', ['lider', 'integrante'])->where('p.activo', true)->pluck('p.id')->all(),
            'residencies' => \App\Support\CareerScope::constrain(DB::table('residencias'), 'residencias', $career)->where('asesor_id', $user)->where('estado', '!=', 'cerrada')->pluck('id')->all(),
        ];
    }

    public function ensureReleased(UserCareer $membership): void
    {
        DB::table('recursos_agenda')->insertOrIgnore(['id' => 'docente:'.$membership->usuario_id]);
        DB::table('recursos_agenda')->where('id', 'docente:'.$membership->usuario_id)->lockForUpdate()->first();
        $pending = $this->commitments($membership->usuario_id, (int) $membership->carrera_id);
        if (collect($pending)->contains(fn ($items) => count($items) > 0)) {
            $list = collect($this->describe($membership->usuario_id, (int) $membership->carrera_id))->map(fn ($item) => $item['label'])->take(6)->implode('; ');
            abort(409, "Tiene pendientes en la carrera: {$list}. Libéralos o pásalos a otro docente antes de desactivarlo o cambiar su perfil.");
        }
    }

    public function hasCommitments(string $user, int $career): bool
    {
        return collect($this->commitments($user, $career))->contains(fn ($items) => count($items) > 0);
    }

    /** Los pendientes con nombre legible, para mostrarlos antes de liberarlos o reasignarlos. */
    public function describe(string $user, int $career): array
    {
        $ids = $this->commitments($user, $career);
        $items = [];
        foreach (DB::table('cursos as c')->join('asignaturas as a', 'a.id', '=', 'c.asignatura_id')->join('grupos_academicos as g', 'g.id', '=', 'c.grupo_id')
            ->whereIn('c.id', $ids['courses'])->get(['a.nombre', 'g.nombre as grupo']) as $row) {
            $items[] = ['type' => 'course', 'label' => "Curso {$row->nombre} ({$row->grupo})"];
        }
        foreach (DB::table('salas_evaluacion')->whereIn('id', $ids['rooms'])->get(['nombre', 'inicia_en', 'responsable_id']) as $row) {
            $responsible = (string) $row->responsable_id === $user ? ', responsable' : '';
            $items[] = ['type' => 'room', 'label' => 'Sala '.($row->nombre ?: 'sin nombre').($row->inicia_en ? ' del '.\Illuminate\Support\Carbon::parse($row->inicia_en)->format('d/m/Y') : '').$responsible];
        }
        foreach (DB::table('proyectos')->whereIn('id', $ids['projects'])->pluck('titulo') as $title) {
            $items[] = ['type' => 'project', 'label' => "Asesoría de «{$title}»"];
        }
        foreach (DB::table('residencias as r')->leftJoin('usuarios as u', 'u.id', '=', 'r.estudiante_id')->whereIn('r.id', $ids['residencies'])
            ->get(['u.nombres', 'u.apellido_paterno']) as $row) {
            $items[] = ['type' => 'residency', 'label' => 'Residencia de '.trim(($row->nombres ?? '').' '.($row->apellido_paterno ?? ''))];
        }

        return $items;
    }

    /**
     * Libera los pendientes del docente en la carrera. Con $replacement se pasan a ese docente (cursos, salas, responsable
     * de sala, asesorías y residencias); sin él se dan de baja, salvo las residencias, que siempre necesitan un asesor.
     */
    public function release(string $user, int $career, ?string $replacement, string $actor): array
    {
        $ids = $this->commitments($user, $career);
        abort_if($ids['residencies'] && !$replacement, 422, 'Asesora residencias en curso: elige a otro docente para pasárselas.');
        abort_if($replacement === $user, 422, 'Elige a un docente distinto para recibir los pendientes.');
        if ($replacement) {
            $valid = UserCareer::where('usuario_id', $replacement)->where('carrera_id', $career)->where('activo', true)
                ->whereNotIn('perfil_id', [3])->exists();
            abort_unless($valid, 422, 'El docente que recibe los pendientes debe estar activo en esta carrera.');
        }
        $described = $this->describe($user, $career);

        DB::transaction(function () use ($ids, $user, $replacement) {
            foreach (DB::table('curso_docentes')->whereIn('curso_id', $ids['courses'])->where('docente_id', $user)->where('activo', true)->get() as $row) {
                DB::table('curso_docentes')->where('curso_id', $row->curso_id)->where('docente_id', $user)->update(['activo' => false]);
                if ($replacement) {
                    DB::table('curso_docentes')->updateOrInsert(['curso_id' => $row->curso_id, 'docente_id' => $replacement],
                        ['labor' => $row->labor, 'activo' => true, 'asignado_en' => now()]);
                }
            }
            foreach ($ids['rooms'] as $room) {
                $mine = DB::table('sala_docentes')->where('sala_id', $room)->where('docente_id', $user)->first();
                DB::table('sala_docentes')->where('sala_id', $room)->where('docente_id', $user)->delete();
                if ($replacement && $mine && !DB::table('sala_docentes')->where('sala_id', $room)->where('docente_id', $replacement)->exists()) {
                    DB::table('sala_docentes')->insert(['sala_id' => $room, 'docente_id' => $replacement, 'rol' => $mine->rol]);
                }
                $responsible = $replacement ?: DB::table('sala_docentes')->where('sala_id', $room)->value('docente_id');
                DB::table('salas_evaluacion')->where('id', $room)->where('responsable_id', $user)->update(['responsable_id' => $responsible]);
            }
            foreach ($ids['projects'] as $project) {
                $mine = DB::table('proyecto_integrantes')->where('proyecto_id', $project)->where('usuario_id', $user)->first();
                DB::table('proyecto_integrantes')->where('proyecto_id', $project)->where('usuario_id', $user)->delete();
                if ($replacement && $mine && !DB::table('proyecto_integrantes')->where('proyecto_id', $project)->where('usuario_id', $replacement)->exists()) {
                    // cargo_unico es columna generada: sólo se copian rol y fechas.
                    DB::table('proyecto_integrantes')->insert(['proyecto_id' => $project, 'usuario_id' => $replacement, 'rol' => $mine->rol, 'agregado_en' => now()]);
                }
            }
            if ($ids['residencies']) {
                DB::table('residencias')->whereIn('id', $ids['residencies'])->update(['asesor_id' => $replacement, 'updated_at' => now()]);
            }
        });
        app(AcademicWorkflowService::class)->audit('docente.pendientes_liberados', 'usuario', $user, null,
            ['recibe' => $replacement, 'pendientes' => array_column($described, 'label'), 'por' => $actor]);

        return $described;
    }
}
