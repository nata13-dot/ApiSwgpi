<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_actividad', function (Blueprint $table) {
            $table->id();
            $table->string('usuario_id', 10);
            $table->string('actor_id', 10)->nullable();
            $table->string('tipo', 50);
            $table->string('titulo', 180);
            $table->text('mensaje');
            $table->string('url', 500);
            $table->timestamp('leida_en')->nullable();
            $table->timestamp('creada_en')->useCurrent();

            $table->index(['usuario_id', 'leida_en', 'creada_en'], 'idx_notificaciones_usuario_estado');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_actividad');
    }
};
