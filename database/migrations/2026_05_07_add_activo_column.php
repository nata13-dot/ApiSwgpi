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
        // Agregar columna 'activo' a competencias si no existe
        if (Schema::hasTable('competencias') && !Schema::hasColumn('competencias', 'activo')) {
            Schema::table('competencias', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('asignatura_id');
                $table->index('activo');
            });
        }

        // Agregar columna 'activo' a asignaturas si no existe
        if (Schema::hasTable('asignaturas') && !Schema::hasColumn('asignaturas', 'activo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('codigo');
                $table->index('activo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo eliminar si existen
        if (Schema::hasTable('competencias') && Schema::hasColumn('competencias', 'activo')) {
            Schema::table('competencias', function (Blueprint $table) {
                $table->dropIndex(['activo']);
                $table->dropColumn('activo');
            });
        }

        if (Schema::hasTable('asignaturas') && Schema::hasColumn('asignaturas', 'activo')) {
            Schema::table('asignaturas', function (Blueprint $table) {
                $table->dropIndex(['activo']);
                $table->dropColumn('activo');
            });
        }
    }
};
