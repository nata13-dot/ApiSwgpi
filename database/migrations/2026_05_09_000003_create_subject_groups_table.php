<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subject_groups')) {
            Schema::create('subject_groups', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->unsignedTinyInteger('semestre');
                $table->string('periodo')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['semestre', 'activo']);
            });
        }

        if (!Schema::hasTable('subject_group_asignatura')) {
            Schema::create('subject_group_asignatura', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
                $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['subject_group_id', 'asignatura_id'], 'subject_group_asignatura_unique');
            });
        }

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'subject_group_id')) {
                $table->foreignId('subject_group_id')->nullable()->after('semestre')->constrained('subject_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'subject_group_id')) {
                $table->dropConstrainedForeignId('subject_group_id');
            }
        });

        Schema::dropIfExists('subject_group_asignatura');
        Schema::dropIfExists('subject_groups');
    }
};