<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento de tesis (App\Services\ThesisTrackingService): un curso por alumno sobre su tesis.
 * - tesis_cursos: el curso (tesis, alumno, estado y liberación).
 * - tesis_capitulos: los capítulos definidos al crearlo.
 * - tesis_revisores: los n docentes que revisan (director, codirector, revisores de la tesis y extra).
 * - tesis_plan_entregas: las entregas planeadas, con fecha y capítulos esperados.
 * - tesis_envios / tesis_envio_capitulos: cada documento que sube el alumno y qué capítulos cubre (historial).
 * - tesis_revisiones: puntaje 0–100 y comentario de cada docente por capítulo de un envío.
 * - tesis_anotaciones: notas de un docente sobre una página del documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tesis_cursos')) {
            Schema::create('tesis_cursos', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('carrera_id');
                $table->unsignedBigInteger('proyecto_id');
                $table->string('estudiante_id', 20);
                $table->string('titulo', 255);
                $table->text('descripcion')->nullable();
                $table->enum('estado', ['activo', 'liberada', 'cerrado'])->default('activo');
                $table->dateTime('liberada_en')->nullable();
                $table->string('liberada_por', 20)->nullable();
                $table->string('creado_por', 20)->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->dateTime('actualizado_en')->nullable();
                $table->unique(['proyecto_id', 'estudiante_id']);
                $table->index(['carrera_id', 'estado']);
                $table->foreign('proyecto_id')->references('id')->on('proyectos')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('tesis_capitulos')) {
            Schema::create('tesis_capitulos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curso_id')->constrained('tesis_cursos')->cascadeOnDelete();
                $table->unsignedTinyInteger('numero');
                $table->string('titulo', 200);
                $table->boolean('activo')->default(true);
            });
        }
        if (!Schema::hasTable('tesis_revisores')) {
            Schema::create('tesis_revisores', function (Blueprint $table) {
                $table->foreignId('curso_id')->constrained('tesis_cursos')->cascadeOnDelete();
                $table->string('docente_id', 20);
                $table->string('rol', 30)->default('revisor');
                $table->boolean('activo')->default(true);
                $table->dateTime('agregado_en')->useCurrent();
                $table->primary(['curso_id', 'docente_id']);
            });
        }
        if (!Schema::hasTable('tesis_plan_entregas')) {
            Schema::create('tesis_plan_entregas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curso_id')->constrained('tesis_cursos')->cascadeOnDelete();
                $table->unsignedTinyInteger('numero');
                $table->string('titulo', 150);
                $table->dateTime('fecha_limite')->nullable();
                $table->json('capitulos')->nullable();
            });
        }
        if (!Schema::hasTable('tesis_envios')) {
            Schema::create('tesis_envios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curso_id')->constrained('tesis_cursos')->cascadeOnDelete();
                $table->foreignId('plan_id')->nullable()->constrained('tesis_plan_entregas')->nullOnDelete();
                $table->unsignedBigInteger('documento_id');
                $table->unsignedSmallInteger('numero');
                $table->string('comentario', 2000)->nullable();
                $table->string('enviado_por', 20)->nullable();
                $table->dateTime('enviado_en')->useCurrent();
                $table->foreign('documento_id')->references('id')->on('documentos')->cascadeOnDelete();
            });
        }
        if (!Schema::hasTable('tesis_envio_capitulos')) {
            Schema::create('tesis_envio_capitulos', function (Blueprint $table) {
                $table->foreignId('envio_id')->constrained('tesis_envios')->cascadeOnDelete();
                $table->foreignId('capitulo_id')->constrained('tesis_capitulos')->cascadeOnDelete();
                $table->primary(['envio_id', 'capitulo_id']);
            });
        }
        if (!Schema::hasTable('tesis_revisiones')) {
            Schema::create('tesis_revisiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('envio_id')->constrained('tesis_envios')->cascadeOnDelete();
                $table->foreignId('capitulo_id')->constrained('tesis_capitulos')->cascadeOnDelete();
                $table->string('docente_id', 20);
                $table->decimal('puntaje', 5, 2);
                $table->text('comentario')->nullable();
                $table->dateTime('creado_en')->useCurrent();
                $table->dateTime('actualizado_en')->nullable();
                $table->unique(['envio_id', 'capitulo_id', 'docente_id']);
            });
        }
        if (!Schema::hasTable('tesis_anotaciones')) {
            Schema::create('tesis_anotaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('envio_id')->constrained('tesis_envios')->cascadeOnDelete();
                $table->foreignId('capitulo_id')->nullable()->constrained('tesis_capitulos')->nullOnDelete();
                $table->string('docente_id', 20);
                $table->unsignedSmallInteger('pagina');
                $table->text('texto');
                $table->dateTime('creado_en')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        foreach (['tesis_anotaciones', 'tesis_revisiones', 'tesis_envio_capitulos', 'tesis_envios', 'tesis_plan_entregas', 'tesis_revisores', 'tesis_capitulos', 'tesis_cursos'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
