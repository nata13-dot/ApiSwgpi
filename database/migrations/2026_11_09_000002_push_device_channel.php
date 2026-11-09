<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Canal de Android de cada teléfono: lleva el tono y la vibración que eligió la persona. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('dispositivos_push', 'canal')) {
            Schema::table('dispositivos_push', function (Blueprint $table) {
                $table->string('canal', 50)->nullable()->after('plataforma');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispositivos_push', 'canal')) {
            Schema::table('dispositivos_push', fn (Blueprint $table) => $table->dropColumn('canal'));
        }
    }
};
