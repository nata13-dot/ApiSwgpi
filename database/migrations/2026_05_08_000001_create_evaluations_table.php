<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->tinyInteger('semestre');
            $table->enum('etapa', ['propuesta', 'avance', 'titulacion']);
            $table->string('sala', 50)->nullable();
            $table->dateTime('fecha_exposicion')->nullable();
            $table->enum('estado', ['programada', 'en_evaluacion', 'finalizada'])->default('programada');
            $table->enum('resultado', ['pendiente', 'viable', 'no_viable'])->default('pendiente');
            $table->string('created_by', 10)->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'semestre', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};