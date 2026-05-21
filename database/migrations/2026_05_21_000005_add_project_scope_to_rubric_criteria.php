<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('semestre');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->index(['semestre', 'project_id', 'activo'], 'rubric_criteria_sem_project_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex('rubric_criteria_sem_project_active_idx');
            $table->dropColumn('project_id');
        });
    }
};
