<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de avances (App\Services\ProjectTrackingService):
 * - materias_seguimiento: materias que la carrera eligió para pedir avances y a qué modalidades aplican.
 * - plantillas_avance: avances base de cada materia (nombre, semana sugerida y ponderación).
 * - curso_proyectos: altas y bajas manuales de un proyecto en un curso, sobre el vínculo automático.
 * - entrega_prorrogas: fecha límite propia de un equipo en un avance.
 * - entrega_ajustes: calificación de un integrante distinta a la del equipo.
 * - entregables.ponderacion / plantilla_id y curso_estudiantes.origen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('materias_seguimiento')) {
            Schema::create('materias_seguimiento', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('carrera_id');
                $table->unsignedBigInteger('asignatura_id');
                $table->boolean('aplica_integrador')->default(true);
                $table->boolean('aplica_caso')->default(false);
                $table->boolean('aplica_dual')->default(false);
                $table->boolean('activo')->default(true);
                $table->string('actualizado_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->dateTime('actualizado_en')->nullable();
                $table->unique(['carrera_id', 'asignatura_id']);
                $table->foreign('asignatura_id')->references('id')->on('asignaturas')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('plantillas_avance')) {
            Schema::create('plantillas_avance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('materia_seguimiento_id')->constrained('materias_seguimiento')->cascadeOnDelete();
                $table->unsignedTinyInteger('orden')->default(1);
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->enum('tipo_documento', ['reporte', 'video', 'presentacion', 'codigo', 'documento', 'otro'])->default('documento');
                $table->unsignedTinyInteger('semana')->nullable();
                $table->decimal('ponderacion', 5, 2)->nullable();
            });
        }
        if (!Schema::hasTable('curso_proyectos')) {
            Schema::create('curso_proyectos', function (Blueprint $table) {
                $table->unsignedBigInteger('curso_id');
                $table->unsignedBigInteger('proyecto_id');
                $table->enum('tipo', ['alta', 'baja']);
                $table->string('motivo', 500);
                $table->string('registrado_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->primary(['curso_id', 'proyecto_id']);
                $table->foreign('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('entrega_prorrogas')) {
            Schema::create('entrega_prorrogas', function (Blueprint $table) {
                $table->unsignedBigInteger('entregable_id');
                $table->unsignedBigInteger('proyecto_id');
                $table->dateTime('vence_en');
                $table->string('motivo', 500);
                $table->string('otorgada_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->primary(['entregable_id', 'proyecto_id']);
                $table->foreign('entregable_id')->references('id')->on('entregables')->cascadeOnDelete();
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('entrega_ajustes')) {
            Schema::create('entrega_ajustes', function (Blueprint $table) {
                $table->unsignedBigInteger('entrega_id');
                $table->string('estudiante_id', 20);
                $table->decimal('calificacion', 5, 2);
                $table->string('motivo', 500);
                $table->string('ajustado_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->primary(['entrega_id', 'estudiante_id']);
                $table->foreign('entrega_id')->references('id')->on('entregas')->cascadeOnDelete();
            });
        }
        Schema::table('entregables', function (Blueprint $table) {
            if (!Schema::hasColumn('entregables', 'ponderacion')) $table->decimal('ponderacion', 5, 2)->nullable()->after('fecha_limite');
            if (!Schema::hasColumn('entregables', 'plantilla_id')) $table->unsignedBigInteger('plantilla_id')->nullable()->after('ponderacion');
        });
        Schema::table('curso_estudiantes', function (Blueprint $table) {
            if (!Schema::hasColumn('curso_estudiantes', 'origen')) $table->enum('origen', ['autoregistro', 'seguimiento', 'manual'])->default('autoregistro')->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('curso_estudiantes', function (Blueprint $table) {
            if (Schema::hasColumn('curso_estudiantes', 'origen')) $table->dropColumn('origen');
        });
        Schema::table('entregables', function (Blueprint $table) {
            foreach (['ponderacion', 'plantilla_id'] as $column) {
                if (Schema::hasColumn('entregables', $column)) $table->dropColumn($column);
            }
        });
        foreach (['entrega_ajustes', 'entrega_prorrogas', 'curso_proyectos', 'plantillas_avance', 'materias_seguimiento'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
