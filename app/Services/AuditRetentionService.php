<?php

namespace App\Services;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Retención de la auditoría. Los registros más antiguos que el plazo (Política de continuidad) se copian a un archivo
 * SQL comprimido dentro de la carpeta de respaldos (database-backups/auditoria), se comprueba la copia y sólo
 * entonces se borran de las tablas. La copia se conserva siempre (no la toca la limpieza de respaldos) y se descarga
 * desde Respaldos; para consultarla se importa en una base de pruebas.
 */
class AuditRetentionService
{
    public const DEFAULT_DAYS = 180;
    public const MIN_DAYS = 30;
    public const MAX_DAYS = 1825;
    /** Tabla => columna de fecha. */
    private const TABLES = ['auditoria_carreras' => 'creado_en', 'bitacora_academica' => 'creado_en', 'bitacora_usuarios' => 'accion_en'];

    public function __construct(private readonly ContinuityPolicyService $policy) {}

    public static function ready(): bool
    {
        return SchemaCache::hasTable('depuraciones_auditoria');
    }

    public function directory(): string
    {
        return storage_path('app/private/database-backups/auditoria');
    }

    public function days(): int
    {
        return max(self::MIN_DAYS, min(self::MAX_DAYS, (int) ($this->policy->get()['audit_retention_days'] ?? self::DEFAULT_DAYS)));
    }

    /** Cuántos registros se depurarían hoy, por tabla. */
    public function preview(): array
    {
        $cutoff = now()->subDays($this->days())->startOfDay();
        $counts = [];
        foreach ($this->tables() as $table => $column) {
            $counts[$table] = DB::table($table)->where($column, '<', $cutoff)->count();
        }

        return ['days' => $this->days(), 'cutoff' => $cutoff->toDateTimeString(), 'pending' => $counts, 'total' => array_sum($counts)];
    }

    public function run(string $source = 'programado', ?string $actorId = null): array
    {
        abort_unless(self::ready(), 409, 'Falta ejecutar la migración de retención de auditoría en la API.');
        $days = $this->days();
        $cutoff = now()->subDays($days)->startOfDay();
        $preview = $this->preview();
        if (!$preview['total']) {
            return ['status' => 'sin_cambios', 'message' => "No hay registros de auditoría de antes del {$cutoff->format('d/m/Y')}.", 'records' => $preview['pending']];
        }
        // Sin una copia completa reciente de la base no se depura: así lo borrado también vive en un respaldo completo.
        $backup = DB::table('respaldos_base_datos')->where('estado', 'completado')->whereNull('eliminado_en')
            ->where('creado_en', '>=', now()->subDays(2))->orderByDesc('id')->first(['id', 'creado_en']);
        abort_unless($backup, 409, 'No hay un respaldo completo de la base de los últimos 2 días; genera uno antes de depurar la auditoría.');

        if (!is_dir($this->directory())) @mkdir($this->directory(), 0750, true);
        abort_unless(is_dir($this->directory()) && is_writable($this->directory()), 500, 'No se puede escribir en la carpeta de respaldos de auditoría.');
        $name = 'auditoria-hasta-'.$cutoff->format('Y-m-d').'-'.now()->format('Ymd-His').'.sql.gz';
        $path = $this->directory().'/'.$name;
        $id = DB::table('depuraciones_auditoria')->insertGetId([
            'corte' => $cutoff, 'retencion_dias' => $days, 'archivo' => $name, 'respaldo_id' => $backup->id,
            'estado' => 'en_proceso', 'origen' => $source, 'creado_por' => $actorId, 'creado_en' => now(),
        ]);

        try {
            [$counts, $limits] = $this->export($path, $cutoff);
            $this->check($path, array_sum($counts));
            foreach ($counts as $table => $count) {
                if (!$count) continue;
                $column = self::TABLES[$table];
                do {
                    $deleted = DB::table($table)->where($column, '<', $cutoff)->where('id', '<=', $limits[$table])->limit(5000)->delete();
                } while ($deleted > 0);
            }
            DB::table('depuraciones_auditoria')->where('id', $id)->update([
                'estado' => 'completada', 'registros' => json_encode($counts), 'tamano_bytes' => filesize($path),
                'sha256' => hash_file('sha256', $path), 'terminado_en' => now(),
            ]);
        } catch (\Throwable $error) {
            @unlink($path);
            DB::table('depuraciones_auditoria')->where('id', $id)->update(['estado' => 'fallida', 'error' => mb_substr($error->getMessage(), 0, 500), 'terminado_en' => now()]);
            throw $error;
        }

        return ['status' => 'completada', 'message' => 'Se copiaron y depuraron '.array_sum($counts)." registros de antes del {$cutoff->format('d/m/Y')}.", 'records' => $counts, 'archive' => $this->find($id)];
    }

    public function history(int $limit = 30): array
    {
        if (!self::ready()) return [];

        return DB::table('depuraciones_auditoria')->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($row) => $this->shape($row))->all();
    }

    public function find(int $id): ?array
    {
        $row = DB::table('depuraciones_auditoria')->find($id);

        return $row ? $this->shape($row) : null;
    }

    public function pathFor(int $id): array
    {
        $row = DB::table('depuraciones_auditoria')->where('id', $id)->where('estado', 'completada')->first();
        abort_unless($row && $row->archivo, 404, 'Esa copia de auditoría no existe.');
        $path = $this->directory().'/'.basename($row->archivo);
        abort_unless(is_file($path), 404, 'El archivo de la copia ya no está en el servidor.');
        abort_unless(hash_equals((string) $row->sha256, hash_file('sha256', $path)), 409, 'El archivo no coincide con su checksum SHA-256: está alterado o dañado.');

        return [$path, $row];
    }

    private function shape(object $row): array
    {
        $records = json_decode((string) $row->registros, true) ?: [];

        return [
            'id' => $row->id, 'cutoff' => $row->corte, 'retention_days' => $row->retencion_dias, 'file' => $row->archivo,
            'size_bytes' => (int) $row->tamano_bytes, 'sha256' => $row->sha256, 'records' => $records, 'total' => array_sum($records),
            'backup_id' => $row->respaldo_id, 'status' => $row->estado, 'error' => $row->error, 'source' => $row->origen,
            'created_at' => $row->creado_en, 'finished_at' => $row->terminado_en,
            'available' => $row->estado === 'completada' && $row->archivo && is_file($this->directory().'/'.basename($row->archivo)),
        ];
    }

    private function tables(): array
    {
        return array_filter(self::TABLES, fn ($column, $table) => SchemaCache::hasTable($table), ARRAY_FILTER_USE_BOTH);
    }

    /** Escribe INSERT IGNORE por cada fila vieja (se pueden reimportar sin duplicar). Devuelve [conteos, id máximo]. */
    private function export(string $path, $cutoff): array
    {
        $gz = gzopen($path, 'wb6');
        abort_unless($gz, 500, 'No se pudo crear el archivo de la copia de auditoría.');
        $pdo = DB::connection()->getPdo();
        $counts = [];
        $limits = [];
        gzwrite($gz, "-- SGPI: copia de auditoría depurada. Registros anteriores a {$cutoff->toDateTimeString()}. Generada ".now()->toDateTimeString()."\n-- Importar en una base de pruebas: gunzip -c archivo.sql.gz | mysql base_pruebas\nSET NAMES utf8mb4;\n");
        try {
            foreach ($this->tables() as $table => $column) {
                $counts[$table] = 0;
                $limits[$table] = 0;
                $columns = null;
                DB::table($table)->where($column, '<', $cutoff)->orderBy('id')->chunkById(1000, function ($rows) use ($gz, $pdo, $table, &$counts, &$limits, &$columns) {
                    foreach ($rows as $row) {
                        $data = (array) $row;
                        $columns ??= '`'.implode('`, `', array_keys($data)).'`';
                        $values = implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $data));
                        gzwrite($gz, "INSERT IGNORE INTO `{$table}` ({$columns}) VALUES ({$values});\n");
                        $counts[$table]++;
                        $limits[$table] = max($limits[$table], (int) $data['id']);
                    }
                });
            }
        } finally {
            gzclose($gz);
        }

        return [$counts, $limits];
    }

    /** Relee el archivo y confirma que trae todas las filas antes de borrar nada. */
    private function check(string $path, int $expected): void
    {
        $gz = gzopen($path, 'rb');
        abort_unless($gz, 500, 'No se pudo releer la copia de auditoría.');
        $found = 0;
        while (($line = gzgets($gz)) !== false) {
            if (str_starts_with($line, 'INSERT IGNORE INTO')) $found++;
        }
        gzclose($gz);
        abort_unless($found === $expected, 500, "La copia de auditoría está incompleta ({$found} de {$expected} registros); no se borró nada.");
    }
}
