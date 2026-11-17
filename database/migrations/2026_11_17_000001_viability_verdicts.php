<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dictamen de viabilidad de la evaluación de 5° (App\Services\ViabilityService):
 * - dictamenes_docentes.viabilidad: el voto de cada evaluador en su rúbrica.
 * - evaluaciones.viabilidad*: el dictamen que confirma coordinación o jefatura, con motivo, quién y cuándo.
 * - proyectos.estado "no_viable": el proyecto queda en el historial y el equipo puede registrar otra propuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dictamenes_docentes', function (Blueprint $table) {
            if (!Schema::hasColumn('dictamenes_docentes', 'viabilidad')) $table->enum('viabilidad', ['viable', 'correcciones', 'no_viable'])->nullable();
        });
        Schema::table('evaluaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluaciones', 'viabilidad')) $table->enum('viabilidad', ['viable', 'correcciones', 'no_viable'])->nullable();
            if (!Schema::hasColumn('evaluaciones', 'viabilidad_motivo')) $table->text('viabilidad_motivo')->nullable();
            if (!Schema::hasColumn('evaluaciones', 'viabilidad_confirmada_por')) $table->string('viabilidad_confirmada_por', 20)->nullable();
            if (!Schema::hasColumn('evaluaciones', 'viabilidad_confirmada_en')) $table->dateTime('viabilidad_confirmada_en')->nullable();
        });
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE proyectos MODIFY estado ENUM('borrador','pendiente','en_revision','requiere_cambios','aprobado','rechazado','finalizado','archivado','no_viable') NOT NULL DEFAULT 'borrador'");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('proyectos')->where('estado', 'no_viable')->update(['estado' => 'rechazado']);
            DB::statement("ALTER TABLE proyectos MODIFY estado ENUM('borrador','pendiente','en_revision','requiere_cambios','aprobado','rechazado','finalizado','archivado') NOT NULL DEFAULT 'borrador'");
        }
        Schema::table('evaluaciones', function (Blueprint $table) {
            foreach (['viabilidad', 'viabilidad_motivo', 'viabilidad_confirmada_por', 'viabilidad_confirmada_en'] as $column) {
                if (Schema::hasColumn('evaluaciones', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('dictamenes_docentes', function (Blueprint $table) {
            if (Schema::hasColumn('dictamenes_docentes', 'viabilidad')) $table->dropColumn('viabilidad');
        });
    }
};
