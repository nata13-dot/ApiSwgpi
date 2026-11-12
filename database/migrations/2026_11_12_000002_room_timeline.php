<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turnos de sala con exposición + preguntas y respuestas + tolerancia entre proyectos (App\Support\RoomTimeline):
 * - tolerancia_minutos: minutos entre un proyecto y el siguiente (3 por defecto).
 * - preguntas_automaticas: al terminar la exposición, el temporizador pasa solo a preguntas tras 5 s.
 * - temporizador_fase: exposicion o preguntas.
 * minutos_evaluacion_docente ahora es el tiempo de preguntas y respuestas (los docentes califican durante el turno).
 * La vista v_evaluacion_horario estima cada turno con la duración completa del turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            if (!Schema::hasColumn('salas_evaluacion', 'tolerancia_minutos')) $table->unsignedTinyInteger('tolerancia_minutos')->default(3);
            if (!Schema::hasColumn('salas_evaluacion', 'preguntas_automaticas')) $table->boolean('preguntas_automaticas')->default(true);
            if (!Schema::hasColumn('salas_evaluacion', 'temporizador_fase')) $table->string('temporizador_fase', 12)->default('exposicion');
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_evaluacion_horario AS
                SELECT e.id AS evaluacion_id, e.sala_id, e.proyecto_id, e.orden_presentacion,
                       s.inicia_en AS inicio_sala,
                       s.inicia_en + INTERVAL ((e.orden_presentacion - 1) * (s.minutos_presentacion_proyecto + s.minutos_evaluacion_docente + s.tolerancia_minutos)) MINUTE AS inicio_estimado,
                       CASE e.apto_titulacion WHEN 1 THEN 'viable' WHEN 0 THEN 'no_viable' ELSE 'pendiente' END AS resultado
                FROM evaluaciones e JOIN salas_evaluacion s ON s.id = e.sala_id");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_evaluacion_horario AS
                SELECT e.id AS evaluacion_id, e.sala_id, e.proyecto_id, e.orden_presentacion,
                       s.inicia_en AS inicio_sala,
                       s.inicia_en + INTERVAL ((e.orden_presentacion - 1) * s.minutos_presentacion_proyecto) MINUTE AS inicio_estimado,
                       CASE e.apto_titulacion WHEN 1 THEN 'viable' WHEN 0 THEN 'no_viable' ELSE 'pendiente' END AS resultado
                FROM evaluaciones e JOIN salas_evaluacion s ON s.id = e.sala_id");
        }
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            foreach (['tolerancia_minutos', 'preguntas_automaticas', 'temporizador_fase'] as $column) {
                if (Schema::hasColumn('salas_evaluacion', $column)) $table->dropColumn($column);
            }
        });
    }
};
