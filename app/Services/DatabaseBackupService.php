<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
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
        @set_time_limit(360);

        try {
            $this->run([
                $this->binary(['mysqldump', 'mariadb-dump']),
                ...$this->connectionArguments($connection),
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$temporaryPath,
                (string) ($connection['database'] ?? ''),
            ], $connection, 'generar el respaldo');
            if (!is_file($temporaryPath) || filesize($temporaryPath) === 0) {
                throw new \RuntimeException('La copia de la base de datos quedó vacía.');
            }
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
            $chunk = fread($input, 1024 * 1024);
            if ($chunk === false || ($chunk !== '' && gzwrite($output, $chunk) === false)) {
                fclose($input);
                gzclose($output);
                throw new \RuntimeException('No se pudo comprimir el respaldo: revisa el espacio libre en disco.');
            }
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
        @set_time_limit(360);

        try {
            if (!hash_equals((string) $backup->checksum_sha256, hash_file('sha256', $backup->ruta_privada))) {
                throw new \RuntimeException('El checksum SHA-256 del archivo no coincide con el registrado.');
            }
            $this->gunzip($backup->ruta_privada, $temporarySql);

            try {
                DB::statement("CREATE DATABASE `{$temporaryDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Illuminate\Database\QueryException $exception) {
                $user = config('database.connections.'.config('database.default').'.username');
                throw new \RuntimeException("El usuario de base de datos «{$user}» no puede crear la base temporal del simulacro. "
                    ."Concede el permiso con: GRANT ALL PRIVILEGES ON `sgpi\\_restore\\_check\\_%`.* TO '{$user}'@'localhost';", 0, $exception);
            }
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
            // Sólo cuentan las copias que siguen guardadas: una depurada ya no sirve para restaurar.
            'verified' => DB::table('verificaciones_restauracion as verification')
                ->join('respaldos_base_datos as backup', 'backup.id', '=', 'verification.respaldo_id')
                ->whereNull('backup.eliminado_en')
                ->where('verification.estado', 'correcto')
                ->distinct()
                ->count('verification.respaldo_id'),
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

    /**
     * Descomprime la copia para el simulacro. Quita las cláusulas DEFINER (crear disparadores o vistas a nombre de
     * otro usuario exige privilegios SUPER que la cuenta de la aplicación no tiene) y la marca de "sandbox" que
     * agregan las versiones recientes de mariadb-dump y que el cliente de MySQL no reconoce.
     */
    private function gunzip(string $source, string $destination): void
    {
        $input = gzopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (!$input || !$output) {
            throw new \RuntimeException('No se pudo descomprimir el respaldo.');
        }
        while (($line = gzgets($input)) !== false) {
            if (str_starts_with($line, '/*M!999999')) {
                continue;
            }
            $line = preg_replace('/\sDEFINER\s*=\s*(`[^`]*`|\'[^\']*\'|[^\s@]+)@(`[^`]*`|\'[^\']*\'|\S+)/i', '', $line);
            fwrite($output, $line);
        }
        gzclose($input);
        fclose($output);
        chmod($destination, 0600);
    }

    private function runMysqlImport(string $database, string $sqlPath): void
    {
        $connection = config('database.connections.'.config('database.default'), []);
        $this->run([
            $this->binary(['mysql', 'mariadb']),
            ...$this->connectionArguments($connection),
            '--default-character-set=utf8mb4',
            '--database='.$database,
            '--execute=SOURCE '.$sqlPath,
        ], $connection, 'restaurar la copia en la base temporal');
    }

    /** El cliente de MariaDB 11 ya no instala los alias mysql/mysqldump: se usa el primero que exista. */
    private function binary(array $candidates): string
    {
        $finder = new ExecutableFinder();
        foreach ($candidates as $candidate) {
            if ($path = $finder->find($candidate, null, ['/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin'])) {
                return $path;
            }
        }

        throw new \RuntimeException('No se encontró '.implode(' ni ', $candidates).' en el servidor. Instala el cliente de MySQL o MariaDB.');
    }

    private function connectionArguments(array $connection): array
    {
        if (!empty($connection['unix_socket'])) {
            return ['--socket='.$connection['unix_socket'], '--user='.(string) ($connection['username'] ?? 'root')];
        }

        return [
            '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
            '--port='.(string) ($connection['port'] ?? '3306'),
            '--user='.(string) ($connection['username'] ?? 'root'),
        ];
    }

    /** Ejecuta la herramienta y, si falla, conserva sólo su mensaje de error (sin la línea de comandos). */
    private function run(array $arguments, array $connection, string $task): void
    {
        $process = new Process($arguments, base_path(), ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
        $process->setTimeout(300);
        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
            throw new \RuntimeException("Se agotó el tiempo (5 minutos) al {$task}.");
        }
        if (!$process->isSuccessful()) {
            $detail = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'código de salida '.$process->getExitCode();
            $user = (string) ($connection['username'] ?? '');
            // Los procedimientos los crea root al importar la base; la cuenta de la aplicación necesita leerlos.
            if (str_contains($detail, 'insufficient privileges to SHOW CREATE')) {
                $detail .= " Concede la lectura de rutinas con: GRANT SELECT ON mysql.proc TO '{$user}'@'localhost';";
            }
            throw new \RuntimeException("No se pudo {$task}: ".Str::limit($detail, 700));
        }
    }
}
