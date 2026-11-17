<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Eliminación definitiva de una cuenta. Se borra con su historial propio (matrículas, movimientos, liberaciones,
 * participación en entregas, residencias y autorizaciones a su nombre) y sus archivos de residencia y foto; lo que
 * depende de ella sin ser suyo queda sin autor (la base lo pone en nulo). No se borra a quien dejó registros como
 * autoridad (operaciones académicas, revisiones, solicitudes de autorización o asesorías de residencia): esa cuenta
 * debe ir al archivo histórico para conservar la trazabilidad.
 */
class UserPurgeService
{
    /** @return string|null el motivo por el que no se puede eliminar, o null si se puede */
    public function blocker(User $user): ?string
    {
        $id = (string) $user->getKey();
        $authorship = [
            'operaciones académicas' => DB::table('operaciones_academicas')->where('actor_id', $id)->exists(),
            'revisiones de alumnos irregulares' => DB::table('revisiones_irregulares')->where('respondido_por', $id)->exists(),
            'solicitudes de autorización hechas por la cuenta' => DB::table('autorizaciones_academicas')->where('solicitada_por', $id)->where('estudiante_id', '!=', $id)->exists(),
            'asesorías de residencia' => DB::table('residencias')->where('asesor_id', $id)->exists(),
        ];
        $found = array_keys(array_filter($authorship));

        return $found ? 'Tiene registros como autoridad que la institución debe conservar ('.implode(', ', $found).'); envíalo al archivo histórico.' : null;
    }

    public function purge(User $user): void
    {
        $id = (string) $user->getKey();
        $files = [];
        DB::transaction(function () use ($id, &$files) {
            $residencies = DB::table('residencias')->where('estudiante_id', $id)->pluck('id');
            $requirements = DB::table('residencia_requisitos')->whereIn('residencia_id', $residencies)->pluck('id');
            $files = DB::table('residencia_versiones')->whereIn('requisito_id', $requirements)->get(['disco', 'ruta'])->all();
            DB::table('residencia_versiones')->whereIn('requisito_id', $requirements)->delete();
            DB::table('residencia_requisitos')->whereIn('id', $requirements)->delete();
            DB::table('residencias')->whereIn('id', $residencies)->delete();
            DB::table('autorizaciones_academicas')->where('estudiante_id', $id)->delete();
            foreach (['entrega_participantes', 'liberaciones_documentos_evaluacion' => 'alumno_id', 'matriculas_academicas', 'movimientos_academicos'] as $table => $column) {
                [$table, $column] = is_int($table) ? [$column, 'estudiante_id'] : [$table, $column];
                DB::table($table)->where($column, $id)->delete();
            }
            if (\App\Support\SchemaCache::hasTable('dispositivos_push')) DB::table('dispositivos_push')->where('usuario_id', $id)->delete();
            if (\App\Support\SchemaCache::hasTable('sesiones_app')) DB::table('sesiones_app')->where('usuario_id', $id)->delete();
            // Membresías, grupos, cursos, integrantes de proyecto y notificaciones se van en cascada.
            DB::table('usuarios')->where('id', $id)->delete();
        });
        foreach ($files as $file) {
            rescue(fn () => Storage::disk($file->disco ?: config('filesystems.default'))->delete($file->ruta), null, false);
        }
        if ($user->photo_path) {
            rescue(fn () => Storage::disk('public')->delete($user->photo_path), null, false);
        }
    }
}
