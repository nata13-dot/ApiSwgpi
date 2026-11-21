<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de tesis por los alumnos (App\Http\Controllers\API\ThesisRegistrationController). La tesis es un proyecto
 * tipo «tesis» que nace pendiente de validación; aquí van sus datos propios y las invitaciones al segundo integrante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tesis_registros')) {
            Schema::create('tesis_registros', function (Blueprint $table) {
                $table->unsignedBigInteger('proyecto_id')->primary();
                $table->string('linea_investigacion', 255);
                $table->text('justificacion')->nullable();
                $table->string('registrada_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->dateTime('actualizado_en')->nullable();
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('tesis_invitaciones')) {
            Schema::create('tesis_invitaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('proyecto_id');
                $table->string('estudiante_id', 20);
                $table->enum('estado', ['pendiente', 'aceptada', 'rechazada', 'cancelada'])->default('pendiente');
                $table->dateTime('creada_en')->useCurrent();
                $table->dateTime('respondida_en')->nullable();
                $table->index(['estudiante_id', 'estado']);
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tesis_invitaciones');
        Schema::dropIfExists('tesis_registros');
    }
};
