<?php

namespace App\Support;

/**
 * Búsqueda por palabras: cada palabra debe aparecer en alguna columna, sin importar el orden. Así «juan perez»
 * encuentra a Juan Carlos Pérez aunque cada palabra viva en una columna distinta. Los acentos y mayúsculas los
 * resuelve la intercalación utf8mb4_unicode_ci de las tablas.
 */
final class TextSearch
{
    private const MAX_WORDS = 6;

    /** @return list<string> Patrones LIKE, con los comodines del usuario escapados. */
    public static function patterns(?string $term): array
    {
        $words = preg_split('/\s+/u', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(
            fn (string $word) => '%'.addcslashes(mb_substr($word, 0, 60), '\\%_').'%',
            array_slice($words, 0, self::MAX_WORDS)
        );
    }

    /**
     * @param  list<string>  $columns
     * @param  (callable(mixed, string): void)|null  $alsoMatch  Coincidencias extra por palabra, como relaciones.
     */
    public static function apply($query, ?string $term, array $columns, ?callable $alsoMatch = null)
    {
        foreach (self::patterns($term) as $pattern) {
            $query->where(function ($scope) use ($columns, $pattern, $alsoMatch) {
                foreach ($columns as $column) {
                    $scope->orWhere($column, 'like', $pattern);
                }
                if ($alsoMatch) {
                    $alsoMatch($scope, $pattern);
                }
            });
        }

        return $query;
    }
}
