<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_registration_windows') && !Schema::hasTable('ventanas_registro_proyectos')) {
            Schema::rename('project_registration_windows', 'ventanas_registro_proyectos');
        }

        if (!Schema::hasTable('ventanas_registro_proyectos')) {
            Schema::create('ventanas_registro_proyectos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_academico_id')
                    ->constrained('grupos_academicos')
                    ->cascadeOnDelete();
                $table->dateTime('inicia_en');
                $table->dateTime('termina_en');
                $table->boolean('activo')->default(true);
                $table->text('notas')->nullable();
                $table->timestamp('creado_en')->nullable();
                $table->timestamp('actualizado_en')->nullable();
                $table->index(['grupo_academico_id', 'activo', 'inicia_en', 'termina_en'], 'idx_ventanas_grupo_estado_fechas');
            });

            return;
        }

        Schema::table('ventanas_registro_proyectos', function (Blueprint $table) {
            if (Schema::hasColumn('ventanas_registro_proyectos', 'subject_group_id')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'grupo_academico_id')) {
                $table->renameColumn('subject_group_id', 'grupo_academico_id');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'starts_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'inicia_en')) {
                $table->renameColumn('starts_at', 'inicia_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'ends_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'termina_en')) {
                $table->renameColumn('ends_at', 'termina_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'notes')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'notas')) {
                $table->renameColumn('notes', 'notas');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'created_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'creado_en')) {
                $table->renameColumn('created_at', 'creado_en');
            }
            if (Schema::hasColumn('ventanas_registro_proyectos', 'updated_at')
                && !Schema::hasColumn('ventanas_registro_proyectos', 'actualizado_en')) {
                $table->renameColumn('updated_at', 'actualizado_en');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventanas_registro_proyectos');
    }
};
