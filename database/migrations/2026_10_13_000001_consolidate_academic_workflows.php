<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carrera_periodos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estado', 20)->default('preparacion'); $t->boolean('conciliado')->default(false);
            $t->timestamps(); $t->unique(['carrera_id', 'periodo_id']);
        });
        Schema::create('matriculas_academicas', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estudiante_id', 20); $t->unsignedBigInteger('grupo_id');
            $t->string('estado', 20)->default('activa'); $t->unsignedInteger('version')->default(1);
            $t->timestamps(); $t->unique(['carrera_id', 'periodo_id', 'estudiante_id'], 'uq_matricula_periodo');
            $t->index(['estudiante_id', 'estado']);
        });
        Schema::create('proyecto_periodos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->unsignedBigInteger('proyecto_id'); $t->unsignedBigInteger('grupo_id'); $t->timestamps();
            $t->unique(['proyecto_id', 'periodo_id']);
        });
        Schema::create('operaciones_academicas', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->unsignedSmallInteger('carrera_id');
            $t->unsignedBigInteger('periodo_id'); $t->string('tipo', 40); $t->string('actor_id', 20);
            $t->string('huella', 64); $t->json('resultado'); $t->timestamps();
        });
        Schema::create('movimientos_academicos', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('origen_id'); $t->unsignedBigInteger('destino_id');
            $t->string('estudiante_id', 20); $t->uuid('operacion_id'); $t->string('accion', 20); $t->text('motivo'); $t->timestamps();
            $t->unique(['carrera_id', 'origen_id', 'destino_id', 'estudiante_id'], 'uq_movimiento_alumno');
        });
        Schema::create('autorizaciones_academicas', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->unsignedBigInteger('proyecto_id')->nullable(); $t->string('estudiante_id', 20)->nullable();
            $t->unsignedBigInteger('curso_id')->nullable(); $t->unsignedBigInteger('evaluacion_id')->nullable();
            $t->string('tipo', 40); $t->unsignedTinyInteger('semestre_destino')->nullable();
            $t->string('estado', 20)->default('solicitada'); $t->text('motivo');
            $t->string('solicitada_por', 20); $t->string('resuelta_por', 20)->nullable();
            $t->text('resolucion')->nullable(); $t->dateTime('vigente_hasta');
            $t->dateTime('consumida_en')->nullable(); $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->index(['carrera_id', 'periodo_id', 'estado'], 'idx_autorizacion_carrera_periodo');
        });
        Schema::create('bitacora_academica', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id')->nullable(); $t->string('actor_id', 20)->nullable();
            $t->string('accion', 80); $t->string('entidad', 80); $t->string('entidad_id', 80);
            $t->json('antes')->nullable(); $t->json('despues')->nullable(); $t->timestamp('creado_en')->useCurrent();
        });
        Schema::create('salones_institucionales', function (Blueprint $t) {
            $t->id(); $t->string('nombre', 120); $t->string('clave', 120)->unique(); $t->boolean('activo')->default(true);
        });
        Schema::create('recursos_agenda', function (Blueprint $t) { $t->string('id', 80)->primary(); });
        Schema::create('entrega_participantes', function (Blueprint $t) {
            $t->unsignedBigInteger('entrega_id'); $t->string('estudiante_id', 20);
            $t->primary(['entrega_id', 'estudiante_id']);
        });
        Schema::create('residencias', function (Blueprint $t) {
            $t->id(); $t->unsignedSmallInteger('carrera_id'); $t->unsignedBigInteger('periodo_id');
            $t->string('estudiante_id', 20); $t->unsignedBigInteger('proyecto_id');
            $t->unsignedBigInteger('empresa_id'); $t->string('asesor_id', 20);
            $t->string('origen', 30); $t->text('cambios_propuestos'); $t->string('estado', 20)->default('abierta');
            $t->unsignedInteger('version')->default(1); $t->timestamps();
            $t->unique(['estudiante_id', 'periodo_id']);
        });
        Schema::create('residencia_requisitos', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('residencia_id'); $t->string('nombre', 180);
            $t->boolean('obligatorio')->default(true); $t->dateTime('fecha_limite')->nullable(); $t->timestamps();
        });
        Schema::create('residencia_versiones', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('requisito_id'); $t->unsignedInteger('numero');
            $t->string('disco', 50); $t->string('ruta', 500); $t->string('nombre', 255);
            $t->string('mime', 120); $t->unsignedBigInteger('bytes'); $t->string('sha256', 64);
            $t->string('estado', 25)->default('pendiente'); $t->text('observaciones')->nullable();
            $t->string('revisada_por', 20)->nullable(); $t->timestamps(); $t->unique(['requisito_id', 'numero']);
        });
        Schema::create('avisos_academicos', function (Blueprint $t) { $t->string('clave', 180)->primary(); $t->timestamp('created_at'); });
        Schema::create('ejecuciones_academicas', function (Blueprint $t) {
            $t->id(); $t->string('tarea', 80); $t->string('estado', 20); $t->text('detalle')->nullable(); $t->timestamps();
        });
        Schema::table('cursos', function (Blueprint $t) {
            $t->dateTime('inscripcion_desde')->nullable(); $t->dateTime('inscripcion_hasta')->nullable();
        });
        Schema::table('salas_evaluacion', function (Blueprint $t) {
            foreach (['responsable_id', 'temporizador_actualizado_por', 'horario_actualizado_por'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->string($name, 20)->nullable();
            }
            foreach (['completada_en', 'temporizador_iniciado_en', 'temporizador_finaliza_en', 'evaluacion_fuera_horario_hasta', 'horario_actualizado_en'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->dateTime($name)->nullable();
            }
            foreach (['secuencia_bloqueada', 'permite_evaluacion_fuera_horario'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->boolean($name)->default(false);
            }
            foreach (['orden_actual', 'secuencia_version', 'temporizador_orden', 'temporizador_duracion_segundos', 'temporizador_restante_segundos'] as $name) {
                if (!Schema::hasColumn('salas_evaluacion', $name)) $t->unsignedInteger($name)->default(0);
            }
            if (!Schema::hasColumn('salas_evaluacion', 'temporizador_estado')) $t->string('temporizador_estado', 20)->default('idle');
            if (!Schema::hasColumn('salas_evaluacion', 'motivo_evaluacion_fuera_horario')) $t->text('motivo_evaluacion_fuera_horario')->nullable();
            $t->unsignedBigInteger('periodo_id')->nullable(); $t->unsignedBigInteger('salon_id')->nullable();
        });
        Schema::table('documentos', fn (Blueprint $t) => $t->string('categoria_detalle', 60)->nullable());
        Schema::table('dictamenes_docentes', function (Blueprint $t) {
            if (!Schema::hasColumn('dictamenes_docentes', 'apto_titulacion')) $t->boolean('apto_titulacion')->nullable();
        });
        if (!Schema::hasTable('liberaciones_documentos_evaluacion')) Schema::create('liberaciones_documentos_evaluacion', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('documento_repositorio_id'); $t->string('alumno_id', 20); $t->boolean('liberado')->default(false);
            $t->string('revisado_por', 20)->nullable(); $t->dateTime('revisado_en')->nullable(); $t->timestamps();
            $t->unique(['documento_repositorio_id', 'alumno_id'], 'uq_liberacion_documento_alumno');
            $t->foreign('documento_repositorio_id', 'fk_liberacion_documento_v2')->references('id')->on('documentos')->restrictOnDelete();
        });
        Schema::table('evaluaciones', function (Blueprint $t) {
            $t->json('criterios_snapshot')->nullable(); $t->string('modo_puntaje', 20)->nullable();
            $t->unsignedBigInteger('autorizacion_id')->nullable();
            $t->unsignedBigInteger('presentacion_autorizacion_id')->nullable();
        });
        // Restrict deletes: academic history must not disappear through cascades.
        foreach ([
            'carrera_periodos' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos'],
            'matriculas_academicas' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'estudiante_id' => 'usuarios', 'grupo_id' => 'grupos_academicos'],
            'proyecto_periodos' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'grupo_id' => 'grupos_academicos'],
            'autorizaciones_academicas' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'estudiante_id' => 'usuarios', 'curso_id' => 'cursos', 'evaluacion_id' => 'evaluaciones'],
            'movimientos_academicos' => ['carrera_id' => 'carreras', 'origen_id' => 'periodos_academicos', 'destino_id' => 'periodos_academicos', 'estudiante_id' => 'usuarios'],
            'salas_evaluacion' => ['periodo_id' => 'periodos_academicos', 'salon_id' => 'salones_institucionales'],
            'evaluaciones' => ['autorizacion_id' => 'autorizaciones_academicas', 'presentacion_autorizacion_id' => 'autorizaciones_academicas'],
            'entrega_participantes' => ['entrega_id' => 'entregas', 'estudiante_id' => 'usuarios'],
            'residencias' => ['carrera_id' => 'carreras', 'periodo_id' => 'periodos_academicos', 'proyecto_id' => 'proyectos', 'estudiante_id' => 'usuarios', 'asesor_id' => 'usuarios', 'empresa_id' => 'empresas'],
            'residencia_requisitos' => ['residencia_id' => 'residencias'],
            'residencia_versiones' => ['requisito_id' => 'residencia_requisitos'],
        ] as $table => $references) {
            Schema::table($table, function (Blueprint $t) use ($references) {
                foreach ($references as $column => $target) $t->foreign($column)->references('id')->on($target)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('La consolidación conserva historial: restaurar un respaldo verificado, no eliminar tablas.');
    }
};
