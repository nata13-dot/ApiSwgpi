<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_group_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('teacher_group_assignments', 'asignatura_id')) {
                $table->foreignId('asignatura_id')->nullable()->after('subject_group_id')->constrained('asignaturas')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('teacher_group_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_group_assignments', 'asignatura_id')) {
                $table->dropConstrainedForeignId('asignatura_id');
            }
        });
    }
};