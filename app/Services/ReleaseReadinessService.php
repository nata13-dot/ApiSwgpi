<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class ReleaseReadinessService
{
    public function __construct(
        private readonly MulticareerIntegrityService $integrity,
        private readonly DatabaseBackupService $backups,
    ) {
    }

    public function check(bool $allowLocal = false): array
    {
        $checks = [];
        $this->add($checks, 'Entorno de aplicación', app()->environment('production'), config('app.env'), $allowLocal);
        $this->add($checks, 'Depuración desactivada', !config('app.debug'), config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false', $allowLocal);
        $this->add($checks, 'APP_KEY configurada', mb_strlen((string) config('app.key')) >= 32, 'Secreto presente');
        $this->add($checks, 'JWT_SECRET configurado', mb_strlen((string) config('jwt.secret')) >= 32, 'Secreto presente');

        try {
            DB::selectOne('SELECT 1 AS healthy');
            $this->add($checks, 'Conexión de base de datos', true, 'Disponible');
        } catch (\Throwable $exception) {
            $this->add($checks, 'Conexión de base de datos', false, 'No disponible');
        }

        $requiredTables = [
            'carreras', 'usuario_carrera', 'carrera_modulos', 'configuraciones_carrera',
            'auditoria_carreras', 'ejecuciones_integridad', 'respaldos_base_datos',
            'verificaciones_restauracion', 'alertas_operativas', 'mediciones_continuidad',
            'politica_continuidad',
        ];
        $availableTables = collect($requiredTables)->filter(fn ($table) => DB::getSchemaBuilder()->hasTable($table));
        $this->add(
            $checks,
            'Esquema multicarrera',
            $availableTables->count() === count($requiredTables),
            $availableTables->count().'/'.count($requiredTables).' tablas requeridas'
        );

        $integrity = $this->integrity->report();
        $this->add($checks, 'Aislamiento multicarrera', $integrity['healthy'], "{$integrity['violations']} incidencias");

        $storage = $this->backups->storageStatus();
        $this->add($checks, 'Almacenamiento de respaldos', $storage['healthy'] && $storage['available'] > 0, "{$storage['available']} disponibles");
        $this->add($checks, 'Restauración comprobada', $storage['verified'] > 0, "{$storage['verified']} copias verificadas");
        $this->add($checks, 'Directorio privado escribible', is_writable(storage_path('app/private/database-backups')), 'storage/app/private/database-backups');
        $this->add($checks, 'Espacio libre', ($storage['disk_free_bytes'] ?? 0) >= 1024 ** 3, round(($storage['disk_free_bytes'] ?? 0) / (1024 ** 3), 1).' GB');

        foreach (['mysql', 'mysqldump', 'gzip'] as $binary) {
            $process = new Process(['sh', '-lc', 'command -v '.escapeshellarg($binary)]);
            $process->run();
            $this->add($checks, "Binario {$binary}", $process->isSuccessful(), $process->isSuccessful() ? 'Disponible' : 'No encontrado');
        }

        $audit = new Process(['composer', 'audit', '--locked', '--no-interaction'], base_path());
        $audit->setTimeout(120);
        $audit->run();
        $this->add($checks, 'Dependencias sin avisos conocidos', $audit->isSuccessful(), $audit->isSuccessful() ? 'Sin vulnerabilidades conocidas' : 'Ejecuta composer audit');

        $failures = collect($checks)->where('status', 'fail')->count();
        $warnings = collect($checks)->where('status', 'warning')->count();

        return [
            'ready' => $failures === 0,
            'failures' => $failures,
            'warnings' => $warnings,
            'checks' => $checks,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function add(array &$checks, string $name, bool $passed, string $detail, bool $warning = false): void
    {
        $checks[] = [
            'name' => $name,
            'status' => $passed ? 'ok' : ($warning ? 'warning' : 'fail'),
            'detail' => $detail,
        ];
    }
}
