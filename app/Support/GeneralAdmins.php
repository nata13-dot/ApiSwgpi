<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Administradores generales vigentes (definitivos o temporales no vencidos). No se muestran como personal de las
 * carreras (jefatura, docentes…) aunque conserven su membresía: así no cuentan como jefes ni aparecen en las listas.
 */
class GeneralAdmins
{
    /** @return array<int, string> */
    public static function ids(): array
    {
        return RequestMemo::remember('general-admin-ids', function () {
            if (!SchemaCache::hasColumn('usuarios', 'es_administrador_general')) return [];
            $query = DB::table('usuarios')->where('es_administrador_general', true);
            if (SchemaCache::hasColumn('usuarios', 'admin_general_hasta')) {
                $query->where(fn ($q) => $q->whereNull('admin_general_hasta')->orWhere('admin_general_hasta', '>', now()));
            }

            return $query->pluck('id')->map(fn ($id) => (string) $id)->all();
        });
    }

    /** Quita a los administradores generales de una consulta por la columna del usuario indicada. */
    public static function exclude($query, string $column)
    {
        $ids = self::ids();

        return $ids ? $query->whereNotIn($column, $ids) : $query;
    }
}
