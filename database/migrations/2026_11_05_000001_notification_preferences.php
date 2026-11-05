<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Avisos que cada persona apagó, y notificaciones al teléfono que esperan a que termine el horario de silencio. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('preferencias_notificacion')) {
            Schema::create('preferencias_notificacion', function (Blueprint $table) {
                $table->string('usuario_id', 20);
                $table->string('evento', 50);
                $table->dateTime('creada_en');
                $table->primary(['usuario_id', 'evento']);
            });
        }
        if (!Schema::hasTable('notificaciones_push_pendientes')) {
            Schema::create('notificaciones_push_pendientes', function (Blueprint $table) {
                $table->id();
                $table->string('usuario_id', 20)->index();
                $table->string('titulo', 180);
                $table->text('mensaje');
                $table->string('url', 500)->nullable();
                $table->dateTime('creada_en');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_push_pendientes');
        Schema::dropIfExists('preferencias_notificacion');
    }
};
