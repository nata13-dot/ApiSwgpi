<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function __construct(private readonly ContinuityPolicyService $policy)
    {
    }

    private const REQUIRED_TABLES = [
        'usuarios',
        'carreras',
        'usuario_carrera',
        'proyectos',
        'asignaturas',
        'evaluaciones',
        'configuraciones_sistema',
    ];

    public function create(string $source = 'manual', ?string $actorId = null): array
    {
        $source = in_array($source, ['manual', 'programado', 'consola'], true) ? $source : 'consola';
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        if (!in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('El respaldo automático requiere una conexión MySQL o MariaDB.');
        }

        $directory = storage_path('app/private/database-backups');
        File::ensureDirectoryExists($directory, 0700, true);
        $baseName = 'sgpi_'.now()->format('Ymd_His').'_'.Str::lower(Str::random(6));
        $temporaryPath = $directory.'/'.$baseName.'.sql.tmp';
        $finalName = $baseName.'.sql.gz';
        $finalPath = $directory.'/'.$finalName;

        try {
            $arguments = [
                'mysqldump',
                '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
                '--port='.(string) ($connection['port'] ?? '3306'),
                '--user='.(string) ($connection['username'] ?? 'root'),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file='.$temporaryPath,
                (string) ($connection['database'] ?? ''),
            ];
            $process = new Process($arguments, base_path(), [
                'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
            ]);
            $process->setTimeout(300);
            $process->mustRun();
            $this->gzip($temporaryPath, $finalPath);

            $recordId = DB::table('respaldos_base_datos')->insertGetId([
                'creado_por' => $actorId,
                'origen' => $source,
                'estado' => 'completado',
                'nombre_archivo' => $finalName,
                'ruta_privada' => $finalPath,
                'tamano_bytes' => filesize($finalPath),
                'checksum_sha256' => hash_file('sha256', $finalPath),
                'creado_en' => now(),
            ]);

            return (array) DB::table('respaldos_base_datos')->find($recordId);
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);
            @unlink($finalPath);
            DB::table('respaldos_base_datos')->insert([
                'creado_por' => $actorId,
                'origen' => $source,
                'estado' => 'fallido',
                'mensaje_error' => Str::limit($exception->getMessage(), 1000, ''),
                'creado_en' => now(),
            ]);
            throw $exception;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function gzip(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($destination, 'wb9');
        if (!$input || !$output) {
            throw new \RuntimeException('No se pudo comprimir el respaldo.');
        }
        while (!feof($input)) {
            gzwrite($output, fread($input, 1024 * 1024));
        }
        fclose($input);
        gzclose($output);
        chmod($destination, 0600);
    }

    public function verify(int $backupId, ?string $actorId = null): array
    {
        $backup = DB::table('respaldos_base_datos')
            ->where('id', $backupId)
            ->where('estado', 'completado')
            ->first();
        if (!$backup || !$backup->ruta_privada || !is_file($backup->ruta_privada)) {
            throw new \RuntimeException('El archivo del respaldo no está disponible.');
        }

        $temporaryDatabase = 'sgpi_restore_check_'.Str::lower(Str::random(12));
        $temporarySql = storage_path('app/private/database-backups/'.$temporaryDatabase.'.sql.tmp');
        $tablesFound = 0;
        $rowsVerified = 0;

        try {
            if (!hash_equals((string) $backup->checksum_sha256, hash_file('sha256', $backup->ruta_privada))) {
                throw new \RuntimeException('El checksum SHA-256 del archivo no coincide con el registrado.');
            }
            $this->gunzip($backup->ruta_privada, $temporarySql);

            DB::statement("CREATE DATABASE `{$temporaryDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->runMysqlImport($temporaryDatabase, $temporarySql);

            $existing = DB::table('information_schema.tables')
                ->where('table_schema', $temporaryDatabase)
                ->whereIn('table_name', self::REQUIRED_TABLES)
                ->get(['TABLE_NAME'])
                ->map(fn ($table) => $table->TABLE_NAME)
                ->all();
            $tablesFound = count($existing);
            $missing = array_values(array_diff(self::REQUIRED_TABLES, $existing));
            if ($missing) {
                throw new \RuntimeException('Faltan tablas esenciales: '.implode(', ', $missing).'.');
            }

            foreach (self::REQUIRED_TABLES as $table) {
                $result = DB::selectOne("SELECT COUNT(*) AS total FROM `{$temporaryDatabase}`.`{$table}`");
                $rowsVerified += (int) ($result->total ?? 0);
            }

            $verificationId = DB::table('verificaciones_restauracion')->insertGetId([
                'respaldo_id' => $backupId,
                'verificado_por' => $actorId,
                'estado' => 'correcto',
                'tablas_encontradas' => $tablesFound,
                'filas_verificadas' => $rowsVerified,
                'creado_en' => now(),
            ]);

            return (array) DB::table('verificaciones_restauracion')->find($verificationId);
        } catch (\Throwable $exception) {
            DB::table('verificaciones_restauracion')->insert([
                'respaldo_id' => $backupId,
                'verificado_por' => $actorId,
                'estado' => 'fallido',
                'tablas_encontradas' => $tablesFound,
                'filas_verificadas' => $rowsVerified,
                'mensaje_error' => Str::limit($exception->getMessage(), 1000, ''),
                'creado_en' => now(),
            ]);
            throw $exception;
        } finally {
            @unlink($temporarySql);
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$temporaryDatabase}`");
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function storageStatus(?int $retentionDays = null): array
    {
        $retentionDays ??= $this->policy->get()['backup_retention_days'];
        $retentionDays = max(7, min(365, $retentionDays));
        $records = DB::table('respaldos_base_datos')
            ->whereNull('eliminado_en')
            ->where('estado', 'completado')
            ->orderByDesc('id')
            ->get();
        $available = 0;
        $missing = 0;
        $altered = 0;
        $totalBytes = 0;

        foreach ($records as $record) {
            if (!$record->ruta_privada || !is_file($record->ruta_privada)) {
                $missing++;
                continue;
            }
            $available++;
            $totalBytes += (int) filesize($record->ruta_privada);
            if (!$record->checksum_sha256
                || !hash_equals((string) $record->checksum_sha256, hash_file('sha256', $record->ruta_privada))) {
                $altered++;
            }
        }

        $directory = storage_path('app/private/database-backups');
        $eligible = $this->retentionCandidates($retentionDays);

        return [
            'healthy' => $missing === 0 && $altered === 0,
            'records' => $records->count(),
            'available' => $available,
            'missing' => $missing,
            'altered' => $altered,
            'verified' => DB::table('verificaciones_restauracion')
                ->where('estado', 'correcto')
                ->distinct()
                ->count('respaldo_id'),
            'total_bytes' => $totalBytes,
            'disk_free_bytes' => is_dir($directory) ? disk_free_space($directory) : null,
            'retention_days' => $retentionDays,
            'protected_recent' => min(7, $records->count()),
            'eligible_count' => $eligible->count(),
            'eligible_bytes' => $eligible->sum(fn ($record) => (int) ($record->tamano_bytes ?? 0)),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function cleanup(int $retentionDays, string $actorId): array
    {
        $retentionDays = max(7, min(365, $retentionDays));
        $candidates = $this->retentionCandidates($retentionDays);
        $deleted = 0;
        $releasedBytes = 0;

        foreach ($candidates as $record) {
            $original = $record->ruta_privada;
            $quarantine = $original && is_file($original) ? $original.'.trash-'.Str::lower(Str::random(6)) : null;
            if ($quarantine && !rename($original, $quarantine)) {
                throw new \RuntimeException("No se pudo aislar el respaldo {$record->id} para depurarlo.");
            }
            try {
                DB::table('respaldos_base_datos')->where('id', $record->id)->update([
                    'ruta_privada' => null,
                    'eliminado_en' => now(),
                    'eliminado_por' => $actorId,
                ]);
                if ($quarantine) {
                    @unlink($quarantine);
                }
                $deleted++;
                $releasedBytes += (int) ($record->tamano_bytes ?? 0);
            } catch (\Throwable $exception) {
                if ($quarantine && is_file($quarantine)) {
                    @rename($quarantine, $original);
                }
                throw $exception;
            }
        }

        return [
            'deleted' => $deleted,
            'released_bytes' => $releasedBytes,
            'retention_days' => $retentionDays,
        ];
    }

    private function retentionCandidates(int $retentionDays)
    {
        $protected = DB::table('respaldos_base_datos')
            ->whereNull('eliminado_en')
            ->where('estado', 'completado')
            ->orderByDesc('id')
            ->limit(7)
            ->pluck('id');
        $verified = DB::table('verificaciones_restauracion')
            ->join('respaldos_base_datos', 'respaldos_base_datos.id', '=', 'verificaciones_restauracion.respaldo_id')
            ->whereNull('respaldos_base_datos.eliminado_en')
            ->where('verificaciones_restauracion.estado', 'correcto')
            ->orderByDesc('verificaciones_restauracion.id')
            ->limit(3)
            ->pluck('respaldos_base_datos.id');

        return DB::table('respaldos_base_datos')
            ->whereNull('eliminado_en')
            ->where('estado', 'completado')
            ->where('creado_en', '<', now()->subDays($retentionDays))
            ->whereNotIn('id', $protected->merge($verified)->unique()->all())
            ->orderBy('id')
            ->get();
    }

    private function gunzip(string $source, string $destination): void
    {
        $input = gzopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (!$input || !$output) {
            throw new \RuntimeException('No se pudo descomprimir el respaldo.');
        }
        while (!gzeof($input)) {
            fwrite($output, gzread($input, 1024 * 1024));
        }
        gzclose($input);
        fclose($output);
        chmod($destination, 0600);
    }

    private function runMysqlImport(string $database, string $sqlPath): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        $process = new Process([
            'mysql',
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? 'root'),
            '--database='.$database,
            '--execute=SOURCE '.$sqlPath,
        ], base_path(), [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(300);
        $process->mustRun();
    }
}
