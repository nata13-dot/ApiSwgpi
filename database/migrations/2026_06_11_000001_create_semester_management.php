<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('periodos_academicos', 'promocion_automatica')) {
            Schema::table('periodos_academicos', function (Blueprint $table) {
                $table->boolean('promocion_automatica')->default(false)->after('activo');
            });
        }

        if (!Schema::hasColumn('periodos_academicos', 'promocion_aplicada_en')) {
            Schema::table('periodos_academicos', function (Blueprint $table) {
                $table->timestamp('promocion_aplicada_en')->nullable()->after('promocion_automatica');
            });
        }

        if (!Schema::hasTable('excepciones_presentacion_semestre')) {
            Schema::create('excepciones_presentacion_semestre', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('periodo_id');
                $table->unsignedBigInteger('proyecto_id')->nullable();
                $table->string('usuario_id', 10)->nullable();
                $table->unsignedTinyInteger('semestre_presentacion');
                $table->string('motivo', 500)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamp('creado_en')->nullable();
                $table->timestamp('actualizado_en')->nullable();

                $table->index(['periodo_id', 'semestre_presentacion', 'activo'], 'idx_excepcion_periodo_semestre');
                $table->foreign('periodo_id')->references('id')->on('periodos_academicos')->cascadeOnDelete();
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
                $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('excepciones_presentacion_semestre');

        Schema::table('periodos_academicos', function (Blueprint $table) {
            $table->dropColumn(['promocion_automatica', 'promocion_aplicada_en']);
        });
    }
};
