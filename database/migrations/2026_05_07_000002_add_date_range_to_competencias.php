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
        Schema::table('competencias', function (Blueprint $table) {
            // Agregar campos de fechas para validación de rango
            if (!Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->date('fecha_inicio')->nullable()->after('descripcion');
            }
            
            if (!Schema::hasColumn('competencias', 'fecha_fin')) {
                $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            }
            
            // Índices para búsquedas de rango
            if (!Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->index(['fecha_inicio', 'fecha_fin']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competencias', function (Blueprint $table) {
            if (Schema::hasColumn('competencias', 'fecha_inicio')) {
                $table->dropIndex(['fecha_inicio', 'fecha_fin']);
                $table->dropColumn(['fecha_inicio', 'fecha_fin']);
            }
        });
    }
};
