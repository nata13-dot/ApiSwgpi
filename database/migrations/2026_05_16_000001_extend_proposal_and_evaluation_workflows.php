<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_review_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asignatura_id')->constrained('asignaturas')->cascadeOnDelete();
            $table->foreignId('subject_group_id')->nullable()->constrained('subject_groups')->nullOnDelete();
            $table->string('teacher_id', 10);
            $table->string('student_id', 10);
            $table->text('notes')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['asignatura_id', 'teacher_id', 'student_id'], 'proposal_exception_unique');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_rooms', 'responsible_teacher_id')) {
                $table->string('responsible_teacher_id', 10)->nullable()->after('semestre');
                $table->foreign('responsible_teacher_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('evaluation_rooms', 'sequence_locked')) {
                $table->boolean('sequence_locked')->default(false)->after('max_attempts');
            }
            if (!Schema::hasColumn('evaluation_rooms', 'current_order')) {
                $table->unsignedSmallInteger('current_order')->nullable()->after('sequence_locked');
            }
            if (!Schema::hasColumn('evaluation_rooms', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('current_order');
            }
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_room_project', 'presentation_order')) {
                $table->unsignedSmallInteger('presentation_order')->default(0)->after('project_id');
            }
            if (!Schema::hasColumn('evaluation_room_project', 'status')) {
                $table->string('status', 30)->default('pendiente')->after('presentation_order');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'presentation_order')) {
                $table->unsignedSmallInteger('presentation_order')->default(0)->after('fecha_exposicion');
            }
            if (!Schema::hasColumn('evaluations', 'sequence_status')) {
                $table->string('sequence_status', 30)->default('pendiente')->after('presentation_order');
            }
            if (!Schema::hasColumn('evaluations', 'room_feedback')) {
                $table->text('room_feedback')->nullable()->after('resultado');
            }
            if (!Schema::hasColumn('evaluations', 'feedback_by')) {
                $table->string('feedback_by', 10)->nullable()->after('room_feedback');
                $table->foreign('feedback_by')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('evaluations', 'feedback_at')) {
                $table->timestamp('feedback_at')->nullable()->after('feedback_by');
            }
            if (!Schema::hasColumn('evaluations', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('feedback_at');
            }
        });

        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                $table->text('general_comment')->nullable()->after('attempts_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_attempts', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_attempts', 'general_comment')) {
                $table->dropColumn('general_comment');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            foreach (['finalized_at', 'feedback_at', 'feedback_by', 'room_feedback', 'sequence_status', 'presentation_order'] as $column) {
                if (Schema::hasColumn('evaluations', $column)) {
                    if ($column === 'feedback_by') {
                        $table->dropForeign(['feedback_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            foreach (['status', 'presentation_order'] as $column) {
                if (Schema::hasColumn('evaluation_room_project', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            foreach (['completed_at', 'current_order', 'sequence_locked', 'responsible_teacher_id'] as $column) {
                if (Schema::hasColumn('evaluation_rooms', $column)) {
                    if ($column === 'responsible_teacher_id') {
                        $table->dropForeign(['responsible_teacher_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('proposal_review_exceptions');
    }
};
