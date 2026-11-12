<?php

namespace App\Support;

use App\Models\CareerSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Los alumnos nuevos entran a un semestre que elige la carrera al abrir el autoregistro (5° si no se cambia),
 * en uno de sus grupos (por defecto A, B y C). Enero–junio sólo tiene semestres pares y julio–diciembre impares:
 * 5° y 7° se registran de julio a diciembre y 6° de enero a junio.
 */
final class NewStudentGroups
{
    public const DEFAULT_SEMESTER = 5;
    public const SEMESTERS = [5, 6, 7];
    public const DEFAULT = ['A', 'B', 'C'];

    /** @return string[] */
    public static function letters(int $career): array
    {
        return array_values((array) CareerSetting::valueForCareer($career, 'new_student_groups', self::DEFAULT)) ?: self::DEFAULT;
    }

    public static function semester(int $career): int
    {
        $semester = (int) CareerSetting::valueForCareer($career, 'new_student_semester', self::DEFAULT_SEMESTER);

        return in_array($semester, self::SEMESTERS, true) ? $semester : self::DEFAULT_SEMESTER;
    }

    /** Semestres de alumnos nuevos que existen en el periodo: pares en enero–junio, impares en julio–diciembre. */
    public static function optionsFor(object $period): array
    {
        $even = self::startsInFirstHalf($period);

        return array_values(array_filter(self::SEMESTERS, fn ($semester) => ($semester % 2 === 0) === $even));
    }

    public static function allowedIn(object $period, int $semester): bool
    {
        return in_array($semester, self::optionsFor($period), true);
    }

    /** Texto para explicar qué semestres admite el periodo. */
    public static function halfLabel(object $period): string
    {
        return self::startsInFirstHalf($period) ? 'enero–junio (semestres pares)' : 'julio–diciembre (semestres impares)';
    }

    /** Crea los grupos que falten del semestre de alumnos nuevos en el periodo. */
    public static function ensure(int $career, int $periodId): void
    {
        $semester = self::semester($career);
        foreach (self::letters($career) as $letter) {
            $exists = DB::table('grupos_academicos')->where(['carrera_id' => $career, 'periodo_id' => $periodId, 'semestre' => $semester, 'clave_grupo' => $letter])->exists();
            if (!$exists) {
                DB::table('grupos_academicos')->insert(['carrera_id' => $career, 'periodo_id' => $periodId, 'nombre' => "{$semester}° {$letter}", 'semestre' => $semester,
                    'clave_grupo' => $letter, 'activo' => true, 'creado_en' => now(), 'actualizado_en' => now()]);
            }
        }
    }

    private static function startsInFirstHalf(object $period): bool
    {
        $start = $period->fecha_inicio instanceof \DateTimeInterface ? $period->fecha_inicio : Carbon::parse($period->fecha_inicio);

        return (int) $start->format('n') <= 6;
    }
}
