<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80);
            $table->string('salon', 120)->nullable();
            $table->unsignedTinyInteger('semestre');
            $table->dateTime('fecha_evaluacion')->nullable();
            $table->unsignedSmallInteger('teacher_evaluation_minutes')->default(15);
            $table->unsignedSmallInteger('project_presentation_minutes')->default(20);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['semestre', 'activo']);
        });

        Schema::create('evaluation_room_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_room_id')->constrained('evaluation_rooms')->cascadeOnDelete();
            $table->string('teacher_id', 10);
            $table->timestamps();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['evaluation_room_id', 'teacher_id'], 'room_teacher_unique');
        });

        Schema::create('evaluation_room_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_room_id')->constrained('evaluation_rooms')->cascadeOnDelete();
            $table->unsignedBigInteger('project_id');
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['evaluation_room_id', 'project_id'], 'room_project_unique');
        });

        Schema::create('evaluation_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->string('teacher_id', 10);
            $table->unsignedTinyInteger('attempts_count')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();
            $table->foreign('evaluation_id')->references('id')->on('evaluations')->cascadeOnDelete();
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['evaluation_id', 'teacher_id'], 'evaluation_attempt_teacher_unique');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluations', 'evaluation_room_id')) {
                $table->foreignId('evaluation_room_id')->nullable()->after('project_id')->constrained('evaluation_rooms')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('evaluations', 'evaluation_room_id')) {
                $table->dropConstrainedForeignId('evaluation_room_id');
            }
        });
        Schema::dropIfExists('evaluation_attempts');
        Schema::dropIfExists('evaluation_room_project');
        Schema::dropIfExists('evaluation_room_teacher');
        Schema::dropIfExists('evaluation_rooms');
    }
};
