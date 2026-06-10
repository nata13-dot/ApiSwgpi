<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'semestre')) {
                $table->unsignedTinyInteger('semestre')->nullable()->after('perfil_id');
            }
            if (!Schema::hasColumn('usuarios', 'grupo')) {
                $table->string('grupo', 20)->nullable()->after('semestre');
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->index(['perfil_id', 'semestre', 'grupo', 'activo'], 'idx_usuarios_carga_academica');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('idx_usuarios_carga_academica');
            $table->dropColumn(['semestre', 'grupo']);
        });
    }
};
