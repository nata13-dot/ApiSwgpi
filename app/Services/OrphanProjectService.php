<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Proyectos que se quedan sin alumnos cuando sus integrantes se eliminan o se archivan: dejan de aparecer igual que
 * ellos. Al archivar alumnos, el proyecto va al archivo histórico (y vuelve si se restaura a alguno). Al eliminar
 * alumnos definitivamente, el proyecto se elimina como desde la pantalla de Proyectos (queda inactivo).
 */
class OrphanProjectService
{
    public const AUTO_REASON = 'Automático: sus integrantes se enviaron al archivo histórico.';

    /** Proyectos donde estos usuarios son integrantes (líder o integrante). */
    public function projectsOf(array $userIds): array
    {
        return DB::table('proyecto_integrantes')->whereIn('usuario_id', $userIds)->whereIn('rol', ['lider', 'integrante'])
            ->distinct()->pluck('proyecto_id')->map(fn ($id) => (int) $id)->all();
    }

    /** De estos proyectos, los vigentes que ya no tienen ningún alumno vigente (existente y sin archivar). */
    public function orphaned(array $projectIds): array
    {
        if (!$projectIds) return [];

        return DB::table('proyectos as p')->whereIn('p.id', $projectIds)->where('p.activo', true)->whereNull('p.archivado_en')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('proyecto_integrantes as pi')
                ->join('usuarios as u', 'u.id', '=', 'pi.usuario_id')
                ->whereColumn('pi.proyecto_id', 'p.id')->whereIn('pi.rol', ['lider', 'integrante'])->whereNull('u.archivado_en'))
            ->pluck('p.id')->map(fn ($id) => (int) $id)->all();
    }

    /** @param 'archive'|'delete' $mode */
    public function retire(array $projectIds, string $mode, ?string $actorId, ?int $careerId): array
    {
        $orphans = $this->orphaned($projectIds);
        if (!$orphans) return [];
        // Una residencia abierta necesita su proyecto: esos se dejan como están.
        $orphans = array_values(array_diff($orphans, DB::table('residencias')->whereIn('proyecto_id', $orphans)->where('estado', 'abierta')->pluck('proyecto_id')->map(fn ($id) => (int) $id)->all()));
        if (!$orphans) return [];
        DB::transaction(function () use ($orphans, $mode, $actorId, $careerId) {
            if ($mode === 'archive') {
                DB::table('proyectos')->whereIn('id', $orphans)->update(['archivado_en' => now(), 'archivado_por' => $actorId]);
                foreach ($orphans as $id) {
                    DB::table('archivo_historico')->insert(['tipo' => 'proyectos', 'registro_id' => (string) $id, 'carrera_id' => $careerId,
                        'accion' => 'archivado', 'motivo' => self::AUTO_REASON, 'usuario_id' => $actorId, 'creado_en' => now()]);
                }
            } else {
                DB::table('proyectos')->whereIn('id', $orphans)->update(['activo' => false, 'actualizado_en' => now()]);
                DB::table('solicitudes_union_proyecto')->whereIn('proyecto_id', $orphans)->where('estado', 'pendiente')
                    ->update(['estado' => 'cancelada', 'respondida_en' => now()]);
            }
        });

        return $orphans;
    }

    /** Al restaurar alumnos: sus proyectos que se archivaron solos por ellos vuelven a la lista. */
    public function restoreFor(array $userIds, ?string $actorId, ?int $careerId): array
    {
        $projects = $this->projectsOf($userIds);
        if (!$projects) return [];
        $auto = DB::table('archivo_historico as a')->where('a.tipo', 'proyectos')->where('a.accion', 'archivado')->where('a.motivo', self::AUTO_REASON)
            ->whereIn('a.registro_id', array_map('strval', $projects))
            // Sólo si ese archivado automático es el último movimiento del proyecto.
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('archivo_historico as b')->where('b.tipo', 'proyectos')
                ->whereColumn('b.registro_id', 'a.registro_id')->whereColumn('b.id', '>', 'a.id'))
            ->pluck('a.registro_id')->map(fn ($id) => (int) $id)->all();
        $restore = DB::table('proyectos')->whereIn('id', $auto)->whereNotNull('archivado_en')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!$restore) return [];
        DB::transaction(function () use ($restore, $actorId, $careerId) {
            DB::table('proyectos')->whereIn('id', $restore)->update(['archivado_en' => null, 'archivado_por' => null]);
            foreach ($restore as $id) {
                DB::table('archivo_historico')->insert(['tipo' => 'proyectos', 'registro_id' => (string) $id, 'carrera_id' => $careerId,
                    'accion' => 'restaurado', 'motivo' => 'Automático: se restauró a un integrante.', 'usuario_id' => $actorId, 'creado_en' => now()]);
            }
        });

        return $restore;
    }
}
