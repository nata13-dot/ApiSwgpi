<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Autoregistro de alumnos y registro de propuestas por carrera:
 *   - proyectos.objetivo_general y proyecto_objetivos (objetivos específicos en orden);
 *   - teléfono y correo opcionales del contacto de la empresa;
 *   - solicitudes_union_proyecto: un alumno pide unirse a la propuesta de un compañero y un integrante responde;
 *   - carrera_validadores_propuesta: docentes designados que validan propuestas junto con coordinación y jefatura;
 *   - autoregistros_pendientes: la cuenta se crea hasta que el alumno captura el código enviado a su correo.
 * Las ventanas (autoregistro y propuestas) y la regla del correo viven en configuraciones_carrera.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $text = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

        if (!Schema::hasColumn('proyectos', 'objetivo_general')) {
            DB::statement("ALTER TABLE proyectos ADD COLUMN objetivo_general TEXT {$text} NULL AFTER descripcion");
        }
        if (!Schema::hasTable('proyecto_objetivos')) {
            DB::statement("CREATE TABLE proyecto_objetivos (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                proyecto_id BIGINT UNSIGNED NOT NULL,
                orden SMALLINT UNSIGNED NOT NULL,
                descripcion VARCHAR(1000) {$text} NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_proyecto_objetivo_orden (proyecto_id, orden),
                CONSTRAINT fk_proyecto_objetivos_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!Schema::hasColumn('empresas', 'contacto_telefono')) {
            DB::statement("ALTER TABLE empresas
                ADD COLUMN contacto_telefono VARCHAR(30) {$text} NULL AFTER contacto_cargo,
                ADD COLUMN contacto_correo VARCHAR(255) {$text} NULL AFTER contacto_telefono");
        }

        if (!Schema::hasTable('solicitudes_union_proyecto')) {
            DB::statement("CREATE TABLE solicitudes_union_proyecto (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                proyecto_id BIGINT UNSIGNED NOT NULL,
                estudiante_id VARCHAR(20) {$text} NOT NULL,
                estado ENUM('pendiente', 'aceptada', 'rechazada', 'cancelada') {$text} NOT NULL DEFAULT 'pendiente',
                respondida_por VARCHAR(20) {$text} NULL,
                creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                respondida_en DATETIME NULL,
                pendiente_unica TINYINT GENERATED ALWAYS AS (IF(estado = 'pendiente', 1, NULL)) STORED,
                PRIMARY KEY (id),
                UNIQUE KEY uq_solicitud_union_pendiente (estudiante_id, pendiente_unica),
                KEY idx_solicitud_union_proyecto (proyecto_id, estado),
                CONSTRAINT fk_solicitud_union_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos (id) ON DELETE CASCADE,
                CONSTRAINT fk_solicitud_union_estudiante FOREIGN KEY (estudiante_id) REFERENCES usuarios (id) ON DELETE CASCADE,
                CONSTRAINT fk_solicitud_union_respondio FOREIGN KEY (respondida_por) REFERENCES usuarios (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!Schema::hasTable('carrera_validadores_propuesta')) {
            DB::statement("CREATE TABLE carrera_validadores_propuesta (
                carrera_id SMALLINT UNSIGNED NOT NULL,
                docente_id VARCHAR(20) {$text} NOT NULL,
                asignado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (carrera_id, docente_id),
                KEY idx_validador_propuesta_docente (docente_id),
                CONSTRAINT fk_validador_propuesta_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE,
                CONSTRAINT fk_validador_propuesta_docente FOREIGN KEY (docente_id) REFERENCES usuarios (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        if (!Schema::hasTable('autoregistros_pendientes')) {
            DB::statement("CREATE TABLE autoregistros_pendientes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                carrera_id SMALLINT UNSIGNED NOT NULL,
                matricula VARCHAR(20) {$text} NOT NULL,
                correo VARCHAR(255) {$text} NOT NULL,
                datos JSON NOT NULL,
                codigo VARCHAR(255) {$text} NOT NULL,
                intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
                expira_en DATETIME NOT NULL,
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_autoregistro_matricula (matricula),
                UNIQUE KEY uq_autoregistro_correo (correo),
                CONSTRAINT fk_autoregistro_carrera FOREIGN KEY (carrera_id) REFERENCES carreras (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Sistemas Computacionales: l + (b opcional) + número de control de 8 dígitos que empieza con 222.
        $isc = DB::table('carreras')->where('clave', 'ISC')->value('id');
        if ($isc && !DB::table('configuraciones_carrera')->where(['carrera_id' => $isc, 'clave' => 'self_registration_rule'])->exists()) {
            DB::table('configuraciones_carrera')->insert([
                'carrera_id' => $isc,
                'clave' => 'self_registration_rule',
                'valor' => json_encode(['data' => ['email_prefix' => 'l', 'allow_b' => true, 'number_prefix' => '222', 'digits' => 8, 'domain' => 'smartin.tecnm.mx']]),
                'tipo' => 'array',
                'descripcion' => 'Formato del número de control y del correo institucional para el autoregistro',
                'creada_en' => now(),
                'actualizada_en' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('configuraciones_carrera')->whereIn('clave', ['self_registration_rule', 'self_registration_until', 'proposal_registration_until'])->delete();
        Schema::dropIfExists('autoregistros_pendientes');
        Schema::dropIfExists('carrera_validadores_propuesta');
        Schema::dropIfExists('solicitudes_union_proyecto');
        if (Schema::hasColumn('empresas', 'contacto_telefono')) {
            DB::statement('ALTER TABLE empresas DROP COLUMN contacto_correo, DROP COLUMN contacto_telefono');
        }
        Schema::dropIfExists('proyecto_objetivos');
        if (Schema::hasColumn('proyectos', 'objetivo_general')) {
            DB::statement('ALTER TABLE proyectos DROP COLUMN objetivo_general');
        }
    }
};
