<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro mínimo de usuarios:
 * - CURP y dirección dejan de pedirse; sus valores se archivan en hist_usuarios_datos_personales antes de quitar las columnas.
 * - La contraseña inicial es la matrícula/número de empleado y debe cambiarse en el primer inicio de sesión.
 * - revisiones_irregulares guarda la respuesta semanal del jefe de carrera sobre alumnos en otro semestre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS hist_usuarios_datos_personales (
            usuario_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            curp VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            direccion TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (Schema::hasColumn('usuarios', 'curp')) {
            DB::statement('INSERT IGNORE INTO hist_usuarios_datos_personales (usuario_id, curp, direccion)
                SELECT id, curp, direccion FROM usuarios WHERE curp IS NOT NULL OR direccion IS NOT NULL');
            DB::statement('ALTER TABLE usuarios DROP COLUMN curp, DROP COLUMN direccion');
        }

        if (!Schema::hasColumn('usuarios', 'debe_cambiar_contrasena')) {
            DB::statement('ALTER TABLE usuarios ADD COLUMN debe_cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0 AFTER contrasena');
        }

        DB::statement("CREATE TABLE IF NOT EXISTS revisiones_irregulares (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            carrera_id SMALLINT UNSIGNED NOT NULL,
            periodo_id BIGINT UNSIGNED NOT NULL,
            semana TINYINT UNSIGNED NOT NULL,
            respuesta ENUM('sin_casos','con_casos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            comentario VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
            respondido_por VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_revision_irregular_semana (carrera_id, periodo_id, semana),
            KEY idx_revision_irregular_periodo (periodo_id),
            KEY idx_revision_irregular_usuario (respondido_por),
            CONSTRAINT fk_revision_irregular_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE,
            CONSTRAINT fk_revision_irregular_periodo FOREIGN KEY (periodo_id) REFERENCES periodos_academicos (id) ON DELETE CASCADE,
            CONSTRAINT fk_revision_irregular_usuario FOREIGN KEY (respondido_por) REFERENCES usuarios (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS revisiones_irregulares');
        if (Schema::hasColumn('usuarios', 'debe_cambiar_contrasena')) {
            DB::statement('ALTER TABLE usuarios DROP COLUMN debe_cambiar_contrasena');
        }
        if (!Schema::hasColumn('usuarios', 'curp')) {
            DB::statement('ALTER TABLE usuarios ADD COLUMN curp VARCHAR(20) NULL AFTER telefono, ADD COLUMN direccion TEXT NULL AFTER curp');
            DB::statement('UPDATE usuarios u JOIN hist_usuarios_datos_personales h ON h.usuario_id = u.id SET u.curp = h.curp, u.direccion = h.direccion');
        }
    }
};
