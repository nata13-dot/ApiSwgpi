<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Respaldos y pruebas de restauración en segundo plano (DatabaseBackupService::startCreate / startVerify): la fila se
 * guarda "en_proceso" al pedirla y se completa al terminar, sin depender de que el proxy mantenga abierta la petición.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;
        DB::statement("ALTER TABLE respaldos_base_datos MODIFY estado ENUM('en_proceso','completado','fallido') NOT NULL");
        DB::statement("ALTER TABLE verificaciones_restauracion MODIFY estado ENUM('en_proceso','correcto','fallido') NOT NULL");
    }

    public function down(): void
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) return;
        DB::table('respaldos_base_datos')->where('estado', 'en_proceso')->update(['estado' => 'fallido']);
        DB::table('verificaciones_restauracion')->where('estado', 'en_proceso')->update(['estado' => 'fallido']);
        DB::statement("ALTER TABLE respaldos_base_datos MODIFY estado ENUM('completado','fallido') NOT NULL");
        DB::statement("ALTER TABLE verificaciones_restauracion MODIFY estado ENUM('correcto','fallido') NOT NULL");
    }
};
