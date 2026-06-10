<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            if (!Schema::hasColumn('salas_evaluacion', 'salon')) {
                $table->string('salon', 120)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'fecha_fin_evaluacion')) {
                $table->dateTime('fecha_fin_evaluacion')->nullable()->after('fecha_evaluacion');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'teacher_evaluation_minutes')) {
                $table->unsignedSmallInteger('teacher_evaluation_minutes')->default(15)->after('fecha_fin_evaluacion');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'project_presentation_minutes')) {
                $table->unsignedSmallInteger('project_presentation_minutes')->default(20)->after('teacher_evaluation_minutes');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'max_attempts')) {
                $table->unsignedTinyInteger('max_attempts')->default(1)->after('project_presentation_minutes');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'sequence_locked')) {
                $table->boolean('sequence_locked')->default(false)->after('max_attempts');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'current_order')) {
                $table->unsignedSmallInteger('current_order')->nullable()->after('sequence_locked');
            }
            if (!Schema::hasColumn('salas_evaluacion', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('current_order');
            }
        });

        DB::table('salas_evaluacion')
            ->whereNotNull('fecha_evaluacion')
            ->whereNull('fecha_fin_evaluacion')
            ->update([
                'fecha_fin_evaluacion' => DB::raw('DATE_ADD(fecha_evaluacion, INTERVAL 1 HOUR)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('salas_evaluacion', function (Blueprint $table) {
            foreach ([
                'completed_at',
                'current_order',
                'sequence_locked',
                'max_attempts',
                'project_presentation_minutes',
                'teacher_evaluation_minutes',
                'fecha_fin_evaluacion',
                'salon',
            ] as $column) {
                if (Schema::hasColumn('salas_evaluacion', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
