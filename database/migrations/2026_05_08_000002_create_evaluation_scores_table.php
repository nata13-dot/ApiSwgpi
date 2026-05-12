<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->string('teacher_id', 10);
            $table->string('criterio', 80);
            $table->enum('nivel', ['nada', 'poco', 'bastante', 'mucho']);
            $table->unsignedTinyInteger('puntaje');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('evaluation_id')->references('id')->on('evaluations')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['evaluation_id', 'teacher_id', 'criterio'], 'evaluation_teacher_criterio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};