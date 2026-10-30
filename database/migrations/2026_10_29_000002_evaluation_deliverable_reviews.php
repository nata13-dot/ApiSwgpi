<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revisión de entregas para evaluación por un encargado (administrador general, jefatura, asistencia o
 * coordinación). Una entrega sólo cuenta si está aprobada; al reemplazarla, su revisión se borra y
 * vuelve a quedar pendiente. Una fila por proyecto, periodo y entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS revisiones_entregas_evaluacion (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            proyecto_id BIGINT UNSIGNED NOT NULL,
            periodo_id BIGINT UNSIGNED NOT NULL,
            entrega ENUM('presentation', 'link', 'report') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            estado ENUM('aprobada', 'con_observaciones') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            comentario VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            revisado_por VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            revisado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_revision_entrega (proyecto_id, periodo_id, entrega),
            KEY idx_revision_entrega_periodo (periodo_id),
            KEY idx_revision_entrega_usuario (revisado_por),
            CONSTRAINT fk_revision_entrega_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos (id) ON DELETE CASCADE,
            CONSTRAINT fk_revision_entrega_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_academicos (id),
            CONSTRAINT fk_revision_entrega_usuario FOREIGN KEY (revisado_por) REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement('DROP TABLE IF EXISTS revisiones_entregas_evaluacion');
    }
};
