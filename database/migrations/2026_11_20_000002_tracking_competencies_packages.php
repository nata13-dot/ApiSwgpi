<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de avances, segunda parte:
 * - competencias_materia: las n competencias de cada materia (la tabla "competencias" antigua ya no existe).
 * - plantillas_avance / entregables: competencia a la que pertenece el avance y tipos de archivo aceptados.
 * - paquetes_seguimiento / paquete_materias: conjuntos de materias listos para aplicar a un semestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competencias_materia')) {
            Schema::create('competencias_materia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asignatura_id');
                $table->unsignedTinyInteger('orden')->default(1);
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->index(['asignatura_id', 'activo']);
                $table->foreign('asignatura_id')->references('id')->on('asignaturas')->cascadeOnDelete();
            });
        }
        foreach (['plantillas_avance', 'entregables'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (!Schema::hasColumn($name, 'competencia_id')) {
                    $table->unsignedBigInteger('competencia_id')->nullable();
                    $table->foreign('competencia_id', "{$name}_competencia_fk")->references('id')->on('competencias_materia')->nullOnDelete();
                }
                if (!Schema::hasColumn($name, 'extensiones')) $table->string('extensiones', 255)->nullable();
            });
        }
        if (!Schema::hasTable('paquetes_seguimiento')) {
            Schema::create('paquetes_seguimiento', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('carrera_id');
                $table->string('nombre', 150);
                $table->unsignedTinyInteger('semestre')->nullable();
                $table->string('descripcion', 500)->nullable();
                $table->string('actualizado_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->dateTime('actualizado_en')->nullable();
                $table->index('carrera_id');
            });
        }
        if (!Schema::hasTable('paquete_materias')) {
            Schema::create('paquete_materias', function (Blueprint $table) {
                $table->foreignId('paquete_id')->constrained('paquetes_seguimiento')->cascadeOnDelete();
                $table->unsignedBigInteger('asignatura_id');
                $table->boolean('aplica_integrador')->default(true);
                $table->boolean('aplica_caso')->default(false);
                $table->boolean('aplica_dual')->default(false);
                $table->primary(['paquete_id', 'asignatura_id']);
                $table->foreign('asignatura_id')->references('id')->on('asignaturas')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paquete_materias');
        Schema::dropIfExists('paquetes_seguimiento');
        foreach (['plantillas_avance', 'entregables'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (Schema::hasColumn($name, 'competencia_id')) {
                    $table->dropForeign("{$name}_competencia_fk");
                    $table->dropColumn('competencia_id');
                }
                if (Schema::hasColumn($name, 'extensiones')) $table->dropColumn('extensiones');
            });
        }
        Schema::dropIfExists('competencias_materia');
    }
};
