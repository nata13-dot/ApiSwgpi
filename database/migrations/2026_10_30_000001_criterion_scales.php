<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Escala por pregunta de rúbrica:
 *   acuerdo   — completamente en desacuerdo, en desacuerdo, nulo, de acuerdo, completamente de acuerdo
 *   puntos_5  — del 1 al 5
 *   puntos_10 — del 1 al 10
 * Cada respuesta guarda su escala y su porcentaje (la respuesta más baja vale 0 % y la más alta 100 %),
 * así el promedio es correcto aunque una rúbrica mezcle escalas. Las respuestas anteriores conservan el
 * porcentaje con el que se calificaron (fórmula previa: puntaje entre el máximo de su modo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (!Schema::hasColumn('criterios_rubrica', 'escala')) {
            DB::statement("ALTER TABLE criterios_rubrica ADD COLUMN escala ENUM('acuerdo', 'puntos_5', 'puntos_10') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'acuerdo' AFTER pregunta");
        }
        if (!Schema::hasColumn('respuestas_evaluacion', 'escala')) {
            DB::statement("ALTER TABLE respuestas_evaluacion
                ADD COLUMN escala VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER puntaje,
                ADD COLUMN porcentaje DECIMAL(5,2) NULL AFTER escala");
        }

        // Historial: se guarda el porcentaje que ya tenía cada respuesta para que ningún resultado cambie.
        DB::statement("UPDATE respuestas_evaluacion re
            JOIN dictamenes_docentes d ON d.id = re.dictamen_id
            JOIN evaluaciones e ON e.id = d.evaluacion_id
            LEFT JOIN salas_evaluacion s ON s.id = e.sala_id
            LEFT JOIN rubricas r ON r.id = s.rubrica_id
            SET re.escala = 'historica',
                re.porcentaje = ROUND(re.puntaje / CASE
                    WHEN re.valor IN ('nada', 'poco', 'bastante', 'mucho') THEN 3
                    WHEN COALESCE(e.modo_puntaje, r.modo_puntaje) = 'numeric' THEN 5
                    ELSE 4 END * 100, 2)
            WHERE re.porcentaje IS NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (Schema::hasColumn('respuestas_evaluacion', 'escala')) {
            DB::statement('ALTER TABLE respuestas_evaluacion DROP COLUMN porcentaje, DROP COLUMN escala');
        }
        if (Schema::hasColumn('criterios_rubrica', 'escala')) {
            DB::statement('ALTER TABLE criterios_rubrica DROP COLUMN escala');
        }
    }
};
