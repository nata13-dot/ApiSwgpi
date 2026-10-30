<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entregas para evaluación: además de la hoja de liberación y la presentación, desde 6° se pide
 * un enlace de Drive (video y presentación con video) y el documento de reporte (avance en 6° y 7°,
 * final en 8°). El enlace es uno por proyecto y periodo; la carrera se obtiene del proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS enlaces_evaluacion (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            proyecto_id BIGINT UNSIGNED NOT NULL,
            periodo_id BIGINT UNSIGNED NOT NULL,
            url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            registrado_por VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_en DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_enlace_evaluacion_periodo (proyecto_id, periodo_id),
            KEY idx_enlace_evaluacion_periodo (periodo_id),
            KEY idx_enlace_evaluacion_usuario (registrado_por),
            CONSTRAINT fk_enlace_evaluacion_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos (id) ON DELETE CASCADE,
            CONSTRAINT fk_enlace_evaluacion_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_academicos (id),
            CONSTRAINT fk_enlace_evaluacion_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS enlaces_evaluacion');
    }
};
