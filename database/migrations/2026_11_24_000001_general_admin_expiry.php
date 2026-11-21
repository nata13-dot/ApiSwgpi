<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Administración general temporal: fecha en que vence (null = definitiva).
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'admin_general_hasta')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dateTime('admin_general_hasta')->nullable()->after('es_administrador_general');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'admin_general_hasta')) {
            Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('admin_general_hasta'));
        }
    }
};
