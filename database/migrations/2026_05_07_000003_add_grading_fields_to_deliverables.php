<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            // Agregar campos de calificación
            if (!Schema::hasColumn('deliverables', 'calificacion')) {
                $table->decimal('calificacion', 3, 1)->nullable()->after('archivo_path');
                $table->comment('Calificación de 0-100');
            }
            
            if (!Schema::hasColumn('deliverables', 'fecha_calificacion')) {
                $table->dateTime('fecha_calificacion')->nullable()->after('calificacion');
            }
            
            // Agregar campo para identificar quién calificó
            if (!Schema::hasColumn('deliverables', 'calificado_por')) {
                $table->string('calificado_por', 10)->nullable()->after('fecha_calificacion');
                $table->foreign('calificado_por')->references('id')->on('users')->onDelete('set null');
            }
            
            // Índice para búsquedas de estado
            $table->index(['estado', 'calificacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            if (Schema::hasColumn('deliverables', 'calificado_por')) {
                $table->dropForeign(['calificado_por']);
                $table->dropColumn('calificado_por');
            }
            if (Schema::hasColumn('deliverables', 'fecha_calificacion')) {
                $table->dropColumn('fecha_calificacion');
            }
            if (Schema::hasColumn('deliverables', 'calificacion')) {
                $table->dropColumn('calificacion');
            }
            if (Schema::hasIndex('deliverables', 'deliverables_estado_calificacion_index')) {
                $table->dropIndex('deliverables_estado_calificacion_index');
            }
        });
    }
};
