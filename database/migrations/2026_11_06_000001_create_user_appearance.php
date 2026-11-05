<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tema y tamaño de letra elegidos por cada persona; la acompañan en cualquier dispositivo. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('preferencias_apariencia')) {
            Schema::create('preferencias_apariencia', function (Blueprint $table) {
                $table->string('usuario_id', 20)->primary();
                $table->string('tema', 10)->default('light');
                $table->unsignedSmallInteger('escala_letra')->default(100);
                $table->dateTime('actualizado_en');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('preferencias_apariencia');
    }
};
