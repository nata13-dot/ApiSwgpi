<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones de la app móvil. Su token no vence, así que cada una tiene un registro en la base que permite
 * anularla al cerrar sesión o cambiar la contraseña, aunque se limpie la caché del servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sesiones_app')) {
            return;
        }

        Schema::create('sesiones_app', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('usuario_id', 20)->index();
            $table->dateTime('creada_en');
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('revocada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones_app');
    }
};
