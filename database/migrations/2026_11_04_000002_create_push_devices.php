<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Teléfonos que reciben notificaciones. Un token pertenece a quien inició sesión por última vez en ese teléfono. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispositivos_push')) {
            return;
        }

        Schema::create('dispositivos_push', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id', 20)->index();
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->string('plataforma', 20)->default('android');
            $table->dateTime('creado_en');
            $table->dateTime('actualizado_en');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_push');
    }
};
