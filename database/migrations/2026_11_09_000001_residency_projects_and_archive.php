<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proyectos de 9° marcados como residencia (se listan aparte) y archivo histórico de usuarios,
 * proyectos, propuestas y grupos: lo archivado deja de verse en el sistema hasta restaurarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyectos', 'es_residencia')) {
                $table->boolean('es_residencia')->default(false)->after('modalidad');
                $table->string('residencia_marcada_por', 20)->nullable()->after('es_residencia');
                $table->dateTime('residencia_marcada_en')->nullable()->after('residencia_marcada_por');
                $table->index(['es_residencia', 'activo'], 'idx_proyectos_residencia');
            }
        });

        foreach (['usuarios', 'proyectos', 'grupos_academicos'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'archivado_en')) {
                    $table->dateTime('archivado_en')->nullable();
                    $table->string('archivado_por', 20)->nullable();
                    $table->index('archivado_en', "idx_{$name}_archivado");
                }
            });
        }

        if (!Schema::hasTable('archivo_historico')) {
            Schema::create('archivo_historico', function (Blueprint $table) {
                $table->id();
                $table->string('tipo', 20);
                $table->string('registro_id', 30);
                $table->unsignedSmallInteger('carrera_id')->nullable();
                $table->string('accion', 20);
                $table->string('motivo', 500)->nullable();
                $table->string('usuario_id', 20);
                $table->dateTime('creado_en');
                $table->index(['tipo', 'registro_id'], 'idx_archivo_registro');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivo_historico');
        foreach (['usuarios', 'proyectos', 'grupos_academicos'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'archivado_en')) {
                    $table->dropIndex("idx_{$name}_archivado");
                    $table->dropColumn(['archivado_en', 'archivado_por']);
                }
            });
        }
        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'es_residencia')) {
                $table->dropIndex('idx_proyectos_residencia');
                $table->dropColumn(['es_residencia', 'residencia_marcada_por', 'residencia_marcada_en']);
            }
        });
    }
};
