<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - autoregistros_pendientes: la solicitud pasa por revisión; la cuenta se crea al aprobarla.
 * - Tablas de sistema de Laravel que el respaldo normalizado no trae (sin ellas falla recuperar la contraseña).
 * Es idempotente: se puede ejecutar en bases que ya tengan alguna de estas tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql' && Schema::hasTable('autoregistros_pendientes') && !Schema::hasColumn('autoregistros_pendientes', 'estado')) {
            DB::statement("ALTER TABLE autoregistros_pendientes
                ADD COLUMN estado ENUM('codigo','por_aprobar','rechazada') NOT NULL DEFAULT 'codigo' AFTER datos,
                ADD COLUMN verificado_en DATETIME NULL AFTER expira_en,
                ADD COLUMN revisado_por VARCHAR(20) NULL AFTER verificado_en,
                ADD COLUMN revisado_en DATETIME NULL AFTER revisado_por,
                ADD COLUMN motivo_rechazo VARCHAR(500) NULL AFTER revisado_en,
                ADD KEY idx_autoregistros_revision (estado, carrera_id)");
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('user_id', 20)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Las tablas de sistema pueden existir desde antes de esta migración: no se eliminan al revertir.
    }
};
