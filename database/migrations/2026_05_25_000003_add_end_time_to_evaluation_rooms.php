<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
                $table->dateTime('fecha_fin_evaluacion')->nullable()->after('fecha_evaluacion');
            }
        });

        if (Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
            DB::statement("
                UPDATE evaluation_rooms
                SET fecha_fin_evaluacion = DATE_ADD(fecha_evaluacion, INTERVAL 1 HOUR)
                WHERE fecha_evaluacion IS NOT NULL
                  AND fecha_fin_evaluacion IS NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_rooms', 'fecha_fin_evaluacion')) {
                $table->dropColumn('fecha_fin_evaluacion');
            }
        });
    }
};
