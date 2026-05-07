<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('competencia_id')->nullable();
            $table->string('nombre', 255);
            $table->text('descripcion')->nullable();
            $table->text('autores')->nullable();
            $table->enum('tipo_documento', ['reporte', 'video', 'presentacion', 'codigo', 'documento', 'otro'])->default('documento');
            $table->string('rama_asociada', 100)->nullable();
            $table->enum('estado', ['pendiente', 'enviado', 'revisado', 'aprobado'])->default('pendiente');
            $table->string('archivo_path')->nullable();
            $table->string('submitted_by', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->boolean('activo')->default(true);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('competencia_id')->references('id')->on('competencias')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['project_id', 'estado', 'activo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliverables');
    }
};
