<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excepciones_revision_propuesta', function (Blueprint $table) {
            if (!Schema::hasColumn('excepciones_revision_propuesta', 'asignatura_id')) {
                $table->foreignId('asignatura_id')->nullable()->after('grupo_academico_id')
                    ->constrained('asignaturas')->nullOnDelete();
            }
            if (!Schema::hasColumn('excepciones_revision_propuesta', 'estudiante_id')) {
                $table->string('estudiante_id', 10)->nullable()->after('docente_id');
                $table->foreign('estudiante_id')->references('id')->on('usuarios')->cascadeOnDelete();
            }
        });

        DB::statement('ALTER TABLE excepciones_revision_propuesta MODIFY docente_id VARCHAR(10) NULL');

        Schema::table('proyectos', function (Blueprint $table) {
            if (!Schema::hasColumn('proyectos', 'es_propuesta')) {
                $table->boolean('es_propuesta')->default(false)->after('es_tesis');
                $table->index(['es_propuesta', 'estado_propuesta', 'activo'], 'idx_proyectos_flujo_propuesta');
            }
        });

        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");
        DB::table('proyectos')->where('estado_propuesta', 'aprobada')->update(['estado_propuesta' => 'aprobado']);
        DB::table('proyectos')->where('estado_propuesta', 'rechazada')->update(['estado_propuesta' => 'rechazado']);
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");

        $defaultSubjectId = DB::table('asignaturas')
            ->where('nombre', 'Fundamentos de Ingeniería de Software')
            ->value('id');

        if ($defaultSubjectId) {
            $groupIds = DB::table('grupos_academicos')
                ->where('semestre', 5)
                ->where('activo', true)
                ->pluck('id');

            foreach ($groupIds as $groupId) {
                DB::table('grupos_asignaturas')->updateOrInsert(
                    ['grupo_academico_id' => $groupId, 'asignatura_id' => $defaultSubjectId],
                    ['creado_en' => now(), 'actualizado_en' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','aprobado','rechazado','requiere_cambios') NOT NULL DEFAULT 'pendiente'");
        DB::table('proyectos')->where('estado_propuesta', 'aprobado')->update(['estado_propuesta' => 'aprobada']);
        DB::table('proyectos')->where('estado_propuesta', 'rechazado')->update(['estado_propuesta' => 'rechazada']);
        DB::statement("ALTER TABLE proyectos MODIFY estado_propuesta ENUM('pendiente','en_revision','aprobada','rechazada','requiere_cambios') NOT NULL DEFAULT 'pendiente'");

        Schema::table('proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('proyectos', 'es_propuesta')) {
                $table->dropIndex('idx_proyectos_flujo_propuesta');
                $table->dropColumn('es_propuesta');
            }
        });

        DB::statement('ALTER TABLE excepciones_revision_propuesta MODIFY docente_id VARCHAR(10) NOT NULL');

        Schema::table('excepciones_revision_propuesta', function (Blueprint $table) {
            if (Schema::hasColumn('excepciones_revision_propuesta', 'estudiante_id')) {
                $table->dropForeign(['estudiante_id']);
                $table->dropColumn('estudiante_id');
            }
            if (Schema::hasColumn('excepciones_revision_propuesta', 'asignatura_id')) {
                $table->dropForeign(['asignatura_id']);
                $table->dropColumn('asignatura_id');
            }
        });

    }
};
