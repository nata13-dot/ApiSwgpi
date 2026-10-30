<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * La carrera se guarda sólo en las tablas raíz (grupos, asignaturas, rúbricas, operaciones).
 * El resto la obtiene a través de su padre; este helper concentra esos caminos.
 */
final class CareerScope
{
    // tabla => [columna hacia el padre, tabla padre]; se recorre hasta una tabla con carrera_id propio.
    public const PATHS = [
        'proyectos' => ['grupo_id', 'grupos_academicos'],
        'cursos' => ['grupo_id', 'grupos_academicos'],
        'entregables' => ['curso_id', 'cursos'],
        'salas_evaluacion' => ['rubrica_id', 'rubricas'],
        'evaluaciones' => ['sala_id', 'salas_evaluacion'],
        'matriculas_academicas' => ['grupo_id', 'grupos_academicos'],
        'proyecto_periodos' => ['proyecto_id', 'proyectos'],
        'residencias' => ['proyecto_id', 'proyectos'],
        'movimientos_academicos' => ['operacion_id', 'operaciones_academicas'],
    ];

    /** Ids de $table que pertenecen a la carrera (subconsulta para whereIn). */
    public static function ids(string $table, int $careerId): Builder
    {
        if (!isset(self::PATHS[$table])) {
            return DB::table($table)->select('id')->where('carrera_id', $careerId);
        }
        [$column, $parent] = self::PATHS[$table];

        return DB::table($table)->select('id')->whereIn($column, self::ids($parent, $careerId));
    }

    /** Restringe una consulta sobre $table (con alias opcional) a la carrera, vía su padre. */
    public static function constrain($query, string $table, int $careerId, ?string $alias = null)
    {
        $alias ??= $table;
        if (!isset(self::PATHS[$table])) {
            return $query->where("{$alias}.carrera_id", $careerId);
        }
        [$column, $parent] = self::PATHS[$table];

        return $query->whereIn("{$alias}.{$column}", self::ids($parent, $careerId));
    }

    /** Expresión SQL con la carrera de la fila $alias de $table (para SELECT/WHERE/ORDER). */
    public static function expression(string $table, string $alias): string
    {
        if ($table === 'documentos' || $table === 'autorizaciones_academicas') {
            $parents = ["{$alias}.carrera_id", self::parentExpression('proyectos', "{$alias}.proyecto_id")];
            if ($table === 'autorizaciones_academicas') {
                $parents[] = self::parentExpression('cursos', "{$alias}.curso_id");
            }

            return 'COALESCE(' . implode(', ', $parents) . ')';
        }
        if (!isset(self::PATHS[$table])) {
            return "{$alias}.carrera_id";
        }
        [$column, $parent] = self::PATHS[$table];
        $next = 'cs_' . substr(md5($alias . $parent), 0, 6);

        return "(SELECT " . self::expression($parent, $next) . " FROM {$parent} {$next} WHERE {$next}.id = {$alias}.{$column})";
    }

    private static function parentExpression(string $parent, string $column): string
    {
        $next = 'cs_' . substr(md5($column . $parent), 0, 6);

        return "(SELECT " . self::expression($parent, $next) . " FROM {$parent} {$next} WHERE {$next}.id = {$column})";
    }

    /** Carrera de una fila concreta. */
    public static function of(string $table, int|string|null $id): ?int
    {
        if ($id === null) {
            return null;
        }
        $career = DB::table("{$table} as fila")->where('fila.id', $id)->value(DB::raw(self::expression($table, 'fila')));

        return $career === null ? null : (int) $career;
    }

    /** Matrículas de una carrera y periodo: ambos se leen del grupo. */
    public static function enrollments(int $careerId, int $periodId, string $alias = 'm'): Builder
    {
        $group = "{$alias}_grupo";

        return DB::table("matriculas_academicas as {$alias}")
            ->join("grupos_academicos as {$group}", "{$group}.id", '=', "{$alias}.grupo_id")
            ->where("{$group}.carrera_id", $careerId)->where("{$group}.periodo_id", $periodId)
            ->select("{$alias}.*", "{$group}.carrera_id", "{$group}.periodo_id");
    }

    /** Documentos y autorizaciones: carrera propia si no tienen padre; si lo tienen, la del padre. */
    public static function constrainOptional($query, string $table, int $careerId, ?string $alias = null)
    {
        $alias ??= $table;

        return $query->where(function ($scope) use ($table, $alias, $careerId): void {
            $scope->where("{$alias}.carrera_id", $careerId)
                ->orWhereIn("{$alias}.proyecto_id", self::ids('proyectos', $careerId));
            if ($table === 'autorizaciones_academicas') {
                $scope->orWhereIn("{$alias}.curso_id", self::ids('cursos', $careerId));
            }
        });
    }
}
