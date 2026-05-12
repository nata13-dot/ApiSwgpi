<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('semestre');
            $table->string('clave', 80);
            $table->string('pregunta', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['semestre', 'clave']);
        });

        $defaults = [
            5 => [
                'Planteamiento del problema',
                'Justificacion e impacto',
                'Metodologia propuesta',
                'Viabilidad de desarrollo',
                'Exposicion y defensa de la propuesta',
            ],
            6 => [
                'Avance tecnico respecto a las materias',
                'Cumplimiento de entregables',
                'Aplicacion de retroalimentacion previa',
                'Calidad de documentacion',
                'Exposicion y defensa del avance',
            ],
            7 => [
                'Integracion funcional del proyecto',
                'Profundidad tecnica del avance',
                'Validacion de resultados parciales',
                'Calidad de documentacion',
                'Exposicion y defensa del avance',
            ],
            8 => [
                'Madurez tecnica del proyecto',
                'Evidencia de funcionamiento',
                'Impacto y viabilidad para titulacion',
                'Documentacion final',
                'Exposicion y defensa para titulacion',
            ],
        ];

        foreach ($defaults as $semester => $questions) {
            foreach ($questions as $index => $question) {
                DB::table('rubric_criteria')->insert([
                    'semestre' => $semester,
                    'clave' => Str::slug($question, '_'),
                    'pregunta' => $question,
                    'orden' => $index + 1,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};