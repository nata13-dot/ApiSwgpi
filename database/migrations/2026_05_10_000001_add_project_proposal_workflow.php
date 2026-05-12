<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('perfil_id');
            }
            if (!Schema::hasColumn('users', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
            }
            if (!Schema::hasColumn('users', 'photo_path')) {
                $table->string('photo_path')->nullable()->after('telefonos');
            }
            if (!Schema::hasColumn('users', 'profile_completed_at')) {
                $table->timestamp('profile_completed_at')->nullable()->after('photo_path');
            }
        });

        Schema::create('project_registration_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('activo')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('teacher_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_group_id')->constrained('subject_groups')->cascadeOnDelete();
            $table->string('teacher_id', 10);
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('labor', 120)->default('Revision de propuesta');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['subject_group_id', 'teacher_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'proposal_status')) {
                $table->string('proposal_status', 30)->default('pendiente')->after('activo');
            }
            if (!Schema::hasColumn('projects', 'proposal_reviewed_by')) {
                $table->string('proposal_reviewed_by', 10)->nullable()->after('proposal_status');
                $table->foreign('proposal_reviewed_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('projects', 'proposal_review_comment')) {
                $table->text('proposal_review_comment')->nullable()->after('proposal_reviewed_by');
            }
            if (!Schema::hasColumn('projects', 'proposal_reviewed_at')) {
                $table->timestamp('proposal_reviewed_at')->nullable()->after('proposal_review_comment');
            }
            if (!Schema::hasColumn('projects', 'revision_allowed_until')) {
                $table->dateTime('revision_allowed_until')->nullable()->after('proposal_reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['revision_allowed_until', 'proposal_reviewed_at', 'proposal_review_comment', 'proposal_reviewed_by', 'proposal_status'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('teacher_group_assignments');
        Schema::dropIfExists('project_registration_windows');

        Schema::table('users', function (Blueprint $table) {
            foreach (['profile_completed_at', 'photo_path', 'grupo', 'semestre'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};