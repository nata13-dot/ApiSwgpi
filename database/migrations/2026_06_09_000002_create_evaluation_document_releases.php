<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('liberaciones_documentos_evaluacion')) {
            Schema::create('liberaciones_documentos_evaluacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('documento_repositorio_id');
                $table->string('alumno_id', 10);
                $table->boolean('liberado')->default(false);
                $table->string('revisado_por', 10)->nullable();
                $table->timestamp('revisado_en')->nullable();
                $table->timestamps();
                $table->unique(
                    ['documento_repositorio_id', 'alumno_id'],
                    'uq_liberacion_documento_alumno'
                );
            });
        }

        Schema::table('liberaciones_documentos_evaluacion', function (Blueprint $table) {
            if (!$this->constraintExists('fk_liberacion_documento')) {
                $table->foreign('documento_repositorio_id', 'fk_liberacion_documento')
                    ->references('id')
                    ->on('documentos_repositorio')
                    ->cascadeOnDelete();
            }
            if (!$this->constraintExists('fk_liberacion_alumno')) {
                $table->foreign('alumno_id', 'fk_liberacion_alumno')
                    ->references('id')
                    ->on('usuarios')
                    ->cascadeOnDelete();
            }
            if (!$this->constraintExists('fk_liberacion_revisor')) {
                $table->foreign('revisado_por', 'fk_liberacion_revisor')
                    ->references('id')
                    ->on('usuarios')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liberaciones_documentos_evaluacion');
    }

    private function constraintExists(string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'liberaciones_documentos_evaluacion')
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }
};
