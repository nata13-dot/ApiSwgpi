<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Estilo «cristal» (transparencias) elegido por cada persona; se combina con el tema claro u oscuro. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('preferencias_apariencia') && !Schema::hasColumn('preferencias_apariencia', 'cristal')) {
            Schema::table('preferencias_apariencia', fn (Blueprint $table) => $table->boolean('cristal')->default(false)->after('escala_letra'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('preferencias_apariencia', 'cristal')) {
            Schema::table('preferencias_apariencia', fn (Blueprint $table) => $table->dropColumn('cristal'));
        }
    }
};
