<?php
// No source writes: dump the configured database, or migrate only the supplied isolated socket.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

umask(0077);
try {
    $mode = $argv[1] ?? '';
    $directory = $argv[2] ?? '';
    if (!is_dir($directory) || !str_starts_with(realpath($directory), realpath(storage_path('app/private')).DIRECTORY_SEPARATOR)) throw new RuntimeException('Directorio privado inválido.');
    if ($mode === 'export') {
        $config = DB::connection()->getConfig();
        if (!in_array($config['driver'], ['mysql', 'mariadb'], true)) throw new RuntimeException('Se requiere MySQL.');
        $args = ['mysqldump', '--no-defaults', '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--hex-blob', '--routines', '--triggers', '--result-file='.$directory.'/source.sql'];
        if (!empty($config['unix_socket'])) $args[] = '--socket='.$config['unix_socket'];
        else { $args[] = '--host='.$config['host']; $args[] = '--port='.$config['port']; }
        $args[] = '--user='.$config['username']; $args[] = $config['database'];
        $process = new Process($args, base_path(), ['MYSQL_PWD' => $config['password']]);
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) throw new RuntimeException('Falló la exportación; no se modificó la fuente.');
        file_put_contents($directory.'/source.sha256', hash_file('sha256', $directory.'/source.sql')."\n");
        file_put_contents($directory.'/source-diagnostic.json', json_encode(app(\App\Services\AcademicDiagnosticsService::class)->report(), JSON_PRETTY_PRINT));
        echo "Respaldo privado creado.\n";
    } elseif ($mode === 'files') {
        mkdir($directory.'/documents', 0700);
        $manifest = [];
        foreach (\App\Models\DocumentVersion::withoutGlobalScopes()->get() as $version) {
            try {
                [$disk, $path] = app(\App\Services\ProtectedDownloadService::class)->resolve($version);
                $input = \Illuminate\Support\Facades\Storage::disk($disk)->readStream($path);
                if (!is_resource($input)) throw new RuntimeException('Archivo no disponible.');
                $target = $directory.'/documents/'.(int) $version->id;
                $output = fopen($target, 'wb');
                stream_copy_to_stream($input, $output);
                fclose($input); fclose($output);
                $manifest[] = ['version_id' => $version->id, 'disk' => $disk, 'path' => $path, 'sha256' => hash_file('sha256', $target), 'bytes' => filesize($target), 'status' => 'copied'];
            } catch (Throwable $e) {
                $manifest[] = ['version_id' => $version->id, 'status' => 'unavailable'];
            }
        }
        file_put_contents($directory.'/documents-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $copied = count(array_filter($manifest, fn ($r) => $r['status'] === 'copied'));
        echo 'Versiones documentales: '.count($manifest).'; respaldadas: '.$copied.'; no disponibles: '.(count($manifest)-$copied)."\n";
    } elseif ($mode === 'migrate-copy') {
        $socket = $argv[3] ?? '';
        if (!preg_match('#^/tmp/sgpi-rehearsal\.[A-Za-z0-9]+/mysql.sock$#', $socket) || !file_exists($socket)) throw new RuntimeException('Socket aislado inválido.');
        config(['database.default' => 'rehearsal', 'database.connections.rehearsal' => ['driver' => 'mysql', 'unix_socket' => $socket, 'database' => 'sgpi_rehearsal', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true]]);
        DB::purge('rehearsal');
        if (DB::connection()->getDatabaseName() !== 'sgpi_rehearsal') throw new RuntimeException('Destino inválido.');
        $original = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $table) {
            $name = array_values((array) $table)[0];
            $columns = Schema::getColumnListing($name);
            $rows = DB::table($name)->select($columns)->get()->map(fn ($r) => json_encode($r))->sort()->values()->all();
            $original[$name] = ['columns' => $columns, 'count' => count($rows), 'hash' => hash('sha256', json_encode($rows))];
        }
        foreach (['2026_09_10_000001_add_storage_metadata_to_documento_versiones.php', '2026_10_13_000001_consolidate_academic_workflows.php'] as $migration) (require database_path('migrations/'.$migration))->up();
        $preserved = [];
        foreach ($original as $table => $before) {
            $rows = DB::table($table)->select($before['columns'])->get()->map(fn ($r) => json_encode($r))->sort()->values()->all();
            if ($before['hash'] !== hash('sha256', json_encode($rows))) throw new RuntimeException('Cambió el contenido histórico de '.$table);
            $preserved[$table] = $before['count'];
        }
        $report = app(\App\Services\AcademicDiagnosticsService::class)->report();
        $report['preserved_original_tables'] = $preserved;
        $report['source_dump_sha256'] = hash_file('sha256', $directory.'/source.sql');
        file_put_contents($directory.'/rehearsal-result.json', json_encode($report, JSON_PRETTY_PRINT));
        if (!$report['baseline_ready'] || $report['missing_consolidation']) throw new RuntimeException('El esquema ensayado todavía tiene faltantes; revisa el informe.');
        echo 'Ensayo correcto: '.count($preserved)." tablas originales preservadas, incluidos todos sus valores históricos.\n";
    } else throw new RuntimeException('Modo inválido.');
} catch (Throwable $e) {
    // Never emit connection credentials, SQL values or subprocess arguments.
    fwrite(STDERR, 'Ensayo incompleto: '.get_class($e).". No aplicar a la fuente.\n");
    if (isset($directory) && is_dir($directory)) file_put_contents($directory.'/failure-type.txt', get_class($e));
    exit(1);
}
