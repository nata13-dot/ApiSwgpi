<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Niveles de usuario: 1 Administrador general, 2 Jefe de carrera, 3 Asistente de jefatura,
 * 4 Coordinador de proyectos integradores, 5 Docente, 6 Estudiante.
 * Los id de perfil se conservan (la API y el front los usan); el orden vive en perfiles.nivel.
 * El perfil "Administrador" de carrera desaparece: sus membresías pasan a Jefe de carrera y el
 * perfil anterior queda en hist_membresias_perfil.
 */
return new class extends Migration
{
    private const LEVELS = [
        4 => [1, 'administrador_general', 'Administración de todas las carreras'],
        5 => [2, 'jefe_carrera', 'Responsable académico y administrativo de una carrera'],
        6 => [3, 'asistente_jefe_carrera', 'Apoyo operativo académico del jefe de carrera'],
        7 => [4, 'coordinador_proyectos', 'Responsable del seguimiento de proyectos integradores'],
        2 => [5, 'docente', 'Docente o asesor de proyectos'],
        3 => [6, 'estudiante', 'Estudiante integrante de proyecto'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("CREATE TABLE IF NOT EXISTS hist_membresias_perfil (
            membresia_id BIGINT UNSIGNED NOT NULL,
            usuario_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            carrera_id SMALLINT UNSIGNED NOT NULL,
            perfil_anterior TINYINT UNSIGNED NOT NULL,
            perfil_nuevo TINYINT UNSIGNED NOT NULL,
            archivado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (membresia_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!Schema::hasColumn('perfiles', 'nivel')) {
            DB::statement('ALTER TABLE perfiles ADD COLUMN nivel TINYINT UNSIGNED NULL AFTER id');
        }

        DB::transaction(function (): void {
            foreach (self::LEVELS as $id => [$level, $name, $description]) {
                DB::table('perfiles')->updateOrInsert(['id' => $id], ['nivel' => $level, 'nombre' => $name, 'descripcion' => $description]);
            }

            if (DB::table('perfiles')->where('id', 1)->exists()) {
                DB::statement('INSERT IGNORE INTO hist_membresias_perfil (membresia_id, usuario_id, carrera_id, perfil_anterior, perfil_nuevo)
                    SELECT id, usuario_id, carrera_id, perfil_id, 5 FROM usuario_carrera WHERE perfil_id = 1');
                DB::table('usuario_carrera')->where('perfil_id', 1)->update(['perfil_id' => 5, 'actualizado_en' => now()]);
                DB::table('perfiles')->where('id', 1)->delete();
            }
        });

        DB::statement('ALTER TABLE perfiles MODIFY nivel TINYINT UNSIGNED NOT NULL');
        if (!collect(DB::select("SHOW INDEX FROM perfiles WHERE Key_name = 'uq_perfiles_nivel'"))->count()) {
            DB::statement('ALTER TABLE perfiles ADD UNIQUE KEY uq_perfiles_nivel (nivel)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::transaction(function (): void {
            DB::table('perfiles')->insertOrIgnore(['id' => 1, 'nivel' => 7, 'nombre' => 'administrador', 'descripcion' => 'Acceso total al sistema']);
            DB::statement('UPDATE usuario_carrera uc JOIN hist_membresias_perfil h ON h.membresia_id = uc.id SET uc.perfil_id = h.perfil_anterior');
        });
        DB::statement('ALTER TABLE perfiles DROP INDEX uq_perfiles_nivel, DROP COLUMN nivel');
    }
};
