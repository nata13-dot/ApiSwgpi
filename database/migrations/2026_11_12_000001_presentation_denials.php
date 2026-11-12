<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presentaciones negadas por decisión administrativa en un periodo: a todo el equipo (alumno_id nulo) o a un alumno.
 * Al permitirla de nuevo se llenan retirado_por/retirado_en y la fila se conserva como historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('negaciones_presentacion')) {
            return;
        }
        Schema::create('negaciones_presentacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proyecto_id');
            $table->unsignedBigInteger('periodo_id');
            $table->string('alumno_id', 20)->nullable();
            $table->string('motivo', 1000)->nullable();
            $table->string('negado_por', 20)->nullable();
            $table->dateTime('negado_en');
            $table->string('retirado_por', 20)->nullable();
            $table->dateTime('retirado_en')->nullable();
            $table->index(['proyecto_id', 'periodo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('negaciones_presentacion');
    }
};
