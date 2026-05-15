<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['activo', 'perfil_id'], 'users_activo_perfil_idx');
            $table->index(['perfil_id', 'semestre', 'grupo', 'activo'], 'users_student_group_idx');
            $table->index(['activo', 'perfil_id', 'nombres'], 'users_listing_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->index(['activo', 'semestre', 'created_at'], 'projects_active_semester_created_idx');
            $table->index(['subject_group_id', 'activo'], 'projects_subject_active_idx');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->index(['user_id', 'rol_asesor', 'project_id'], 'project_user_user_role_project_idx');
            $table->index(['project_id', 'rol_asesor', 'user_id'], 'project_user_project_role_user_idx');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            $table->index(['activo', 'fecha_evaluacion'], 'rooms_active_date_idx');
            $table->index(['activo', 'nombre'], 'rooms_active_name_idx');
        });

        Schema::table('evaluation_room_teacher', function (Blueprint $table) {
            $table->index(['teacher_id', 'evaluation_room_id'], 'room_teacher_teacher_room_idx');
        });

        Schema::table('evaluation_room_project', function (Blueprint $table) {
            $table->index(['project_id', 'evaluation_room_id'], 'room_project_project_room_idx');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_room_project', function (Blueprint $table) {
            $table->dropIndex('room_project_project_room_idx');
        });

        Schema::table('evaluation_room_teacher', function (Blueprint $table) {
            $table->dropIndex('room_teacher_teacher_room_idx');
        });

        Schema::table('evaluation_rooms', function (Blueprint $table) {
            $table->dropIndex('rooms_active_date_idx');
            $table->dropIndex('rooms_active_name_idx');
        });

        Schema::table('project_user', function (Blueprint $table) {
            $table->dropIndex('project_user_user_role_project_idx');
            $table->dropIndex('project_user_project_role_user_idx');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_active_semester_created_idx');
            $table->dropIndex('projects_subject_active_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_activo_perfil_idx');
            $table->dropIndex('users_student_group_idx');
            $table->dropIndex('users_listing_idx');
        });
    }
};
