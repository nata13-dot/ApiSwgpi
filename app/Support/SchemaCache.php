<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memoriza las comprobaciones de esquema durante la petición. Sin esto, el código que se
 * adapta a columnas opcionales consultaba information_schema una vez por fila serializada.
 */
final class SchemaCache
{
    private static array $tables = [];
    private static array $columns = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[self::key($table)] ??= Schema::hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::$columns[self::key("{$table}.{$column}")] ??= Schema::hasColumn($table, $column);
    }

    public static function flush(): void
    {
        self::$tables = [];
        self::$columns = [];
    }

    private static function key(string $name): string
    {
        $connection = DB::connection();

        return $connection->getName() . ':' . $connection->getDatabaseName() . ':' . $name;
    }
}
