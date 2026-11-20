<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\SemesterManagementService;
use App\Services\MulticareerIntegrityService;
use App\Services\DatabaseBackupService;
use App\Services\OperationalAlertService;
use App\Services\ContinuityReportService;
use App\Services\ReleaseReadinessService;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sgpi:check-multicareer {--store} {--source=consola}', function (MulticareerIntegrityService $integrity) {
    $report = $integrity->report();
    if ($this->option('store')) {
        $integrity->store($report, (string) $this->option('source'));
    }
    $this->table(
        ['Verificación', 'Estado', 'Incidencias'],
        collect($report['checks'])->map(fn ($check) => [
            $check['name'],
            $check['status'] === 'ok' ? 'OK' : 'REVISAR',
            $check['count'],
        ])->all()
    );
    $this->newLine();
    $this->line($report['healthy']
        ? 'Integridad multicarrera correcta.'
        : "Se detectaron {$report['violations']} incidencias.");

    return $report['healthy'] ? self::SUCCESS : self::FAILURE;
})->purpose('Verifica que no existan relaciones cruzadas entre carreras');

Artisan::command('sgpi:backup-database {--source=consola}', function (DatabaseBackupService $backups) {
    $backup = $backups->create((string) $this->option('source'));
    $this->info("Respaldo creado: {$backup['nombre_archivo']}");
    $this->line("SHA-256: {$backup['checksum_sha256']}");

    return self::SUCCESS;
})->purpose('Genera un respaldo privado y comprimido de la base de datos');

Artisan::command('sgpi:verify-backup {backup?}', function (DatabaseBackupService $backups) {
    $backupId = $this->argument('backup') ?: DB::table('respaldos_base_datos')
        ->where('estado', 'completado')
        ->orderByDesc('id')
        ->value('id');
    if (!$backupId) {
        $this->error('No existe un respaldo completado para verificar.');
        return self::FAILURE;
    }

    $verification = $backups->verify((int) $backupId);
    $this->info("Respaldo {$backupId} restaurado correctamente en entorno temporal.");
    $this->line("Tablas esenciales: {$verification['tablas_encontradas']}");
    $this->line("Filas verificadas: {$verification['filas_verificadas']}");

    return self::SUCCESS;
})->purpose('Prueba un respaldo mediante una restauración temporal aislada');

Artisan::command('sgpi:push-test {usuario}', function (\App\Services\PushNotificationService $push) {
    if (!$push->configured()) {
        $this->error('Falta FIREBASE_CREDENTIALS o el archivo no se puede leer.');
        return self::FAILURE;
    }
    $result = $push->sendToUser((string) $this->argument('usuario'), 'Prueba de SGPI', 'Las notificaciones funcionan en este teléfono.', ['url' => '/dashboard']);
    $this->line(json_encode($result));

    return $result['sent'] > 0 ? self::SUCCESS : self::FAILURE;
})->purpose('Envía una notificación de prueba a los teléfonos de un usuario');

Artisan::command('sgpi:send-reminders {--weekly : Envía los resúmenes de la semana}', function (\App\Services\NotificationSchedulerService $scheduler) {
    $this->line(json_encode($this->option('weekly') ? $scheduler->weekly() : $scheduler->daily()));
})->purpose('Recordatorios de entregas y salas, y resúmenes semanales');

Artisan::command('sgpi:flush-notifications', function (\App\Services\PushNotificationService $push) {
    $this->line('Enviadas: '.$push->flushDeferred());
})->purpose('Envía las notificaciones que esperaron al horario de silencio');

Artisan::command('sgpi:backup-health', function (DatabaseBackupService $backups) {
    $status = $backups->storageStatus();
    $this->table(['Copias', 'Disponibles', 'Faltantes', 'Sin permiso', 'Alteradas', 'Verificadas', 'Huérfanas', 'Bases temporales'], [[
        $status['records'],
        $status['available'],
        $status['missing'],
        $status['unreadable'],
        $status['altered'],
        $status['verified'],
        $status['orphans'],
        $status['leftover_databases'],
    ]]);
    if ($status['unreadable_hint']) $this->warn($status['unreadable_hint']);
    $this->line($status['healthy'] ? 'Almacenamiento de respaldos saludable.' : 'Se requiere revisar el almacenamiento de respaldos.');

    return $status['healthy'] ? self::SUCCESS : self::FAILURE;
})->purpose('Comprueba disponibilidad y checksum de los respaldos privados');

Artisan::command('sgpi:scan-operations', function (OperationalAlertService $alerts) {
    $summary = $alerts->scan();
    $this->line("Abiertas: {$summary['open']} · Atendidas: {$summary['acknowledged']} · Críticas activas: {$summary['critical']}");

    return $summary['critical'] ? self::FAILURE : self::SUCCESS;
})->purpose('Actualiza las alertas de integridad y recuperación institucional');

Artisan::command('sgpi:measure-continuity {--source=consola}', function (ContinuityReportService $reports) {
    $measurement = $reports->store((string) $this->option('source'));
    $this->info("Índice de preparación: {$measurement['indice_preparacion']}%");
    $this->line("Controles: {$measurement['controles_correctos']}/{$measurement['controles_totales']}");

    return self::SUCCESS;
})->purpose('Almacena una medición histórica de continuidad operativa');

Artisan::command('sgpi:release-check {--allow-local}', function (ReleaseReadinessService $readiness) {
    $report = $readiness->check((bool) $this->option('allow-local'));
    $this->table(
        ['Control', 'Estado', 'Detalle'],
        collect($report['checks'])->map(fn ($check) => [
            $check['name'],
            strtoupper($check['status']),
            $check['detail'],
        ])->all()
    );
    $this->newLine();
    $this->line($report['ready']
        ? "Liberación preparada con {$report['warnings']} advertencias."
        : "Liberación bloqueada: {$report['failures']} controles fallidos.");

    return $report['ready'] ? self::SUCCESS : self::FAILURE;
})->purpose('Valida seguridad, esquema, aislamiento y recuperación antes de desplegar');

Artisan::command('sgpi:diagnose-academic {--baseline : Check only prerequisites before the additive migration}', function () {
    $report = app(\App\Services\AcademicDiagnosticsService::class)->report();
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $report['baseline_ready'] && ($this->option('baseline') || !$report['missing_consolidation']) ? self::SUCCESS : self::FAILURE;
})->purpose('Diagnóstico de sólo lectura del esquema y conciliación académica');

Artisan::command('sgpi:prepare-academic-periods', function () {
    $id = DB::table('ejecuciones_academicas')->insertGetId(['tarea' => 'preparar_periodos', 'estado' => 'ejecutando', 'created_at' => now(), 'updated_at' => now()]);
    try {
        app(\App\Services\AcademicMaintenanceService::class)->run();
        DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'completada', 'updated_at' => now()]);
        $this->info('Periodos preparados. No se activaron ni promovieron alumnos.');
    } catch (\Throwable $error) {
        DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'fallida', 'detalle' => get_class($error), 'updated_at' => now()]);
        throw $error;
    }
});

Artisan::command('sgpi:automate-periods {--apply : Guarda los cambios; sin esta opción sólo muestra lo que haría} {--career= : Sólo esta carrera}', function (\App\Services\AcademicPeriodAutomationService $automation) {
    $apply = (bool) $this->option('apply');
    $careers = DB::table('carreras')->where('activa', true)->when($this->option('career'), fn ($query, $career) => $query->where('id', $career))->orderBy('id')->get(['id', 'clave']);
    $id = $apply ? DB::table('ejecuciones_academicas')->insertGetId(['tarea' => 'automatizar_periodos', 'estado' => 'ejecutando', 'created_at' => now(), 'updated_at' => now()]) : null;
    try {
        foreach ($careers as $career) {
            $plan = $apply ? $automation->apply((int) $career->id) : $automation->plan((int) $career->id);
            if (!$plan) {
                $this->line("{$career->clave}: sin cambios.");
                continue;
            }
            $this->line(sprintf('%s: %s · %s · %d suben · %d por acomodar · %d grupos nuevos · %d periodos por cerrar',
                $career->clave, $plan['period']['nombre'], $plan['can_apply'] ? ($apply ? 'abierto' : 'se abriría') : 'ya abierto', count($plan['moves']), count($plan['unplaced']), count($plan['new_groups']), count($plan['close'])));
        }
        if ($id) DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'completada', 'updated_at' => now()]);
    } catch (\Throwable $error) {
        if ($id) DB::table('ejecuciones_academicas')->where('id', $id)->update(['estado' => 'fallida', 'detalle' => get_class($error), 'updated_at' => now()]);
        throw $error;
    }

    return self::SUCCESS;
})->purpose('Abre y cierra periodos por fecha; sin --apply sólo muestra la vista previa');

Schedule::command('sgpi:automate-periods --apply')->hourlyAt(20)->timezone('America/Mexico_City')
    ->name('automate-academic-periods')->withoutOverlapping()->when(fn () => config('academic.period_automation'));

Artisan::command('sgpi:sync-tracking {--career= : Sólo esta carrera}', function (\App\Services\ProjectTrackingService $tracking) {
    if (!$tracking->ready()) { $this->warn('Falta la migración de seguimiento de avances.'); return self::SUCCESS; }
    $careers = $this->option('career') ? [(int) $this->option('career')] : DB::table('carrera_periodos')->whereIn('estado', ['abierto', 'en_cierre'])->pluck('carrera_id')->unique()->all();
    foreach ($careers as $career) {
        [$added, $removed] = $tracking->syncCareer((int) $career);
        $this->line("Carrera {$career}: {$added} alta(s), {$removed} baja(s) en materias de seguimiento.");
    }
    return self::SUCCESS;
})->purpose('Liga los proyectos a las materias de seguimiento de su grupo y modalidad');
Artisan::command('sgpi:normalize-names {--apply : Guarda los cambios; sin esta opción sólo cuenta lo que cambiaría}', function () {
    // Corrige los nombres ya guardados (por ejemplo, alumnos que se registraron en MAYÚSCULAS). No imprime nombres.
    $fields = ['nombres', 'apellido_paterno', 'apellido_materno'];
    $changes = array_fill_keys($fields, 0);
    $people = 0;
    DB::table('usuarios')->orderBy('id')->select(array_merge(['id'], $fields))->chunk(500, function ($rows) use ($fields, &$changes, &$people) {
        foreach ($rows as $row) {
            $update = [];
            foreach ($fields as $field) {
                if ($row->{$field} === null || $row->{$field} === '') continue;
                $formatted = \App\Support\PersonName::format($row->{$field});
                if ($formatted !== $row->{$field}) { $update[$field] = $formatted; $changes[$field]++; }
            }
            if (!$update) continue;
            $people++;
            if ($this->option('apply')) DB::table('usuarios')->where('id', $row->id)->update($update);
        }
    });
    $pending = 0;
    foreach (DB::table('autoregistros_pendientes')->whereIn('estado', ['codigo', 'por_aprobar'])->get(['id', 'datos']) as $request) {
        $data = json_decode((string) $request->datos, true) ?: [];
        $fixed = $data;
        foreach (['nombres', 'apa', 'ama'] as $field) if (!empty($data[$field])) $fixed[$field] = \App\Support\PersonName::format($data[$field]);
        if ($fixed === $data) continue;
        $pending++;
        if ($this->option('apply')) DB::table('autoregistros_pendientes')->where('id', $request->id)->update(['datos' => json_encode($fixed, JSON_UNESCAPED_UNICODE)]);
    }
    $this->info(($this->option('apply') ? 'Corregidos' : 'Por corregir').": {$people} usuario(s) · nombres {$changes['nombres']}, apellido paterno {$changes['apellido_paterno']}, apellido materno {$changes['apellido_materno']} · {$pending} solicitud(es) pendiente(s).");
    if (!$this->option('apply') && ($people || $pending)) $this->line('Para guardarlos: php artisan sgpi:normalize-names --apply');
    return self::SUCCESS;
})->purpose('Pone en formato de nombre propio los nombres y apellidos guardados');
Artisan::command('sgpi:release-token {nombre=compilacion : Para reconocer la llave} {--revoke : Revoca todas las llaves activas}', function () {
    if ($this->option('revoke')) {
        $count = DB::table('app_llaves_publicacion')->whereNull('revocada_en')->update(['revocada_en' => now()]);
        $this->info("Llaves revocadas: {$count}. La publicación automática queda desactivada.");
        return self::SUCCESS;
    }
    $token = bin2hex(random_bytes(32));
    DB::table('app_llaves_publicacion')->insert(['huella' => hash('sha256', $token), 'nombre' => mb_substr((string) $this->argument('nombre'), 0, 80), 'creada_en' => now()]);
    $this->warn('Llave de publicación (se muestra una sola vez; guárdala en la computadora que compila):');
    $this->line($token);
    $this->line('Sólo sirve para publicar versiones de la app. Para desactivarla: php artisan sgpi:release-token --revoke');
    return self::SUCCESS;
})->purpose('Crea una llave para publicar versiones de la app automáticamente');
Schedule::command('sgpi:sync-tracking')->everyThirtyMinutes()->name('sync-tracking')->withoutOverlapping();
Schedule::command('sgpi:send-reminders')->dailyAt('09:00')->timezone('America/Mexico_City')->name('send-reminders')->withoutOverlapping();
Schedule::command('sgpi:send-reminders --weekly')->weeklyOn(1, '08:00')->timezone('America/Mexico_City')->name('send-weekly-digests')->withoutOverlapping();
Schedule::command('sgpi:flush-notifications')->dailyAt('07:05')->timezone('America/Mexico_City')->name('flush-notifications')->withoutOverlapping();

Schedule::command('sgpi:prepare-academic-periods')->hourlyAt(10)->timezone('America/Mexico_City')
    ->name('prepare-academic-periods')->withoutOverlapping();

Schedule::command('sgpi:check-multicareer --store --source=programado')
    ->dailyAt('00:20')
    ->name('check-multicareer-integrity')
    ->withoutOverlapping();

Schedule::command('sgpi:backup-database --source=programado')
    ->dailyAt('01:00')
    ->name('backup-database')
    ->withoutOverlapping();

Artisan::command('sgpi:orphan-projects {--apply : Retira los proyectos listados}', function () {
    // Proyectos vigentes sin ningún alumno vigente: sus integrantes se eliminaron o se archivaron antes de que el
    // sistema los retirara solo. Sin --apply sólo se listan para revisarlos.
    $service = app(\App\Services\OrphanProjectService::class);
    $ids = $service->orphaned(DB::table('proyectos')->where('activo', true)->whereNull('archivado_en')->pluck('id')->map(fn ($id) => (int) $id)->all());
    if (!$ids) { $this->info('No hay proyectos sin alumnos.'); return self::SUCCESS; }
    $withArchived = DB::table('proyecto_integrantes')->whereIn('proyecto_id', $ids)->whereIn('rol', ['lider', 'integrante'])->distinct()->pluck('proyecto_id')->map(fn ($id) => (int) $id)->all();
    $rows = DB::table('proyectos as p')->leftJoin('carreras as c', 'c.id', '=', DB::raw('(SELECT g.carrera_id FROM grupos_academicos g WHERE g.id = p.grupo_id)'))
        ->whereIn('p.id', $ids)->orderBy('p.id')->get(['p.id', 'p.titulo', 'p.semestre', 'p.creado_en', 'c.clave']);
    $this->table(['ID', 'Carrera', 'Sem.', 'Creado', 'Situación', 'Título'], $rows->map(fn ($row) => [
        $row->id, $row->clave ?? '—', $row->semestre ?? '—', substr((string) $row->creado_en, 0, 10),
        in_array((int) $row->id, $withArchived, true) ? 'alumnos archivados → archivo' : 'sin integrantes → eliminar',
        mb_strimwidth((string) $row->titulo, 0, 60, '…'),
    ])->all());
    if (!$this->option('apply')) {
        $this->line(count($ids).' proyecto(s). Revisa la lista y vuelve a correr con --apply para retirarlos.');
        return self::SUCCESS;
    }
    $archived = $service->retire($withArchived, 'archive', null, null);
    $deleted = $service->retire(array_values(array_diff($ids, $withArchived)), 'delete', null, null);
    $this->info(count($archived).' enviados al archivo histórico y '.count($deleted).' eliminados (inactivos).');

    return self::SUCCESS;
})->purpose('Lista o retira los proyectos que se quedaron sin alumnos');

Artisan::command('sgpi:prune-audit', function () {
    $result = app(\App\Services\AuditRetentionService::class)->run('programado');
    $this->info($result['message']);

    return self::SUCCESS;
})->purpose('Copia la auditoría vencida junto a los respaldos y la borra de las tablas');

// Después del respaldo de la 01:00: lo que se depura también queda en ese respaldo completo.
Schedule::command('sgpi:prune-audit')
    ->dailyAt('01:40')
    ->timezone('America/Mexico_City')
    ->name('prune-audit')
    ->withoutOverlapping();

Schedule::command('sgpi:verify-backup')
    ->weeklyOn(0, '02:00')
    ->name('verify-latest-database-backup')
    ->withoutOverlapping();

Schedule::command('sgpi:backup-health')
    ->dailyAt('01:30')
    ->name('check-database-backup-storage')
    ->withoutOverlapping();

Schedule::command('sgpi:scan-operations')
    ->dailyAt('02:30')
    ->name('scan-operational-alerts')
    ->withoutOverlapping();

Schedule::command('sgpi:measure-continuity --source=programado')
    ->dailyAt('02:15')
    ->name('measure-operational-continuity')
    ->withoutOverlapping();
