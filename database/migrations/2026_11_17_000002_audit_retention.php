<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retención de la auditoría: los registros más antiguos que el plazo se copian a un archivo comprimido junto a los
 * respaldos de la base de datos y después se borran de las tablas. Cada depuración queda registrada aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('politica_continuidad') && !Schema::hasColumn('politica_continuidad', 'retencion_auditoria_dias')) {
            Schema::table('politica_continuidad', function (Blueprint $table) {
                $table->unsignedSmallInteger('retencion_auditoria_dias')->nullable()->after('retencion_respaldos_dias');
            });
        }
        if (!Schema::hasTable('depuraciones_auditoria')) {
            Schema::create('depuraciones_auditoria', function (Blueprint $table) {
                $table->id();
                $table->dateTime('corte');
                $table->unsignedSmallInteger('retencion_dias');
                $table->string('archivo', 190)->nullable();
                $table->unsignedBigInteger('tamano_bytes')->default(0);
                $table->char('sha256', 64)->nullable();
                $table->json('registros')->nullable();
                $table->unsignedBigInteger('respaldo_id')->nullable();
                $table->enum('estado', ['en_proceso', 'completada', 'fallida'])->default('en_proceso');
                $table->string('error', 500)->nullable();
                $table->string('origen', 20)->default('programado');
                $table->string('creado_por', 10)->nullable();
                $table->dateTime('creado_en');
                $table->dateTime('terminado_en')->nullable();
                $table->index('creado_en');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('depuraciones_auditoria');
        if (Schema::hasColumn('politica_continuidad', 'retencion_auditoria_dias')) {
            Schema::table('politica_continuidad', fn (Blueprint $table) => $table->dropColumn('retencion_auditoria_dias'));
        }
    }
};
