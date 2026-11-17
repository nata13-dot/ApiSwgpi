<?php

namespace App\Support;

use App\Models\CareerSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Los alumnos nuevos entran a uno de los semestres que elige la carrera al abrir el autoregistro (5° si no se elige
 * nada), en uno de sus grupos (por defecto A, B y C). Enero–junio sólo tiene semestres pares y julio–diciembre impares:
 * 5°, 7° y 9° se registran de julio a diciembre; 6° y 8°, de enero a junio.
 */
final class NewStudentGroups
{
    public const DEFAULT_SEMESTER = 5;
    public const SEMESTERS = [5, 6, 7, 8, 9];
    public const DEFAULT = ['A', 'B', 'C'];

    /** @return string[] */
    public static function letters(int $career): array
    {
        return array_values((array) CareerSetting::valueForCareer($career, 'new_student_groups', self::DEFAULT)) ?: self::DEFAULT;
    }

    /** Semestres elegidos al abrir el autoregistro (los ajustes anteriores guardaban uno solo). */
    public static function semesters(int $career): array
    {
        $chosen = CareerSetting::valueForCareer($career, 'new_student_semesters', null);
        $list = is_array($chosen) && $chosen ? $chosen : [CareerSetting::valueForCareer($career, 'new_student_semester', self::DEFAULT_SEMESTER)];

        return self::clean($list) ?: [self::DEFAULT_SEMESTER];
    }

    /** El primero de los elegidos; para lo que todavía trabaja con un solo semestre. */
    public static function semester(int $career): int
    {
        return self::semesters($career)[0];
    }

    /** Los elegidos que existen en el periodo (pares o impares según su mitad del año). */
    public static function activeSemesters(int $career, object $period): array
    {
        return array_values(array_intersect(self::semesters($career), self::optionsFor($period)));
    }

    /** Sin elegir nada: 5°, o el primero que admita el periodo. */
    public static function defaultFor(object $period): array
    {
        $options = self::optionsFor($period);

        return [in_array(self::DEFAULT_SEMESTER, $options, true) ? self::DEFAULT_SEMESTER : ($options[0] ?? self::DEFAULT_SEMESTER)];
    }

    /** @return int[] semestres válidos, sin repetir y en orden */
    public static function clean(array $semesters): array
    {
        $list = array_values(array_unique(array_filter(array_map('intval', $semesters), fn ($semester) => in_array($semester, self::SEMESTERS, true))));
        sort($list);

        return $list;
    }

    /** "5°", "5° y 7°", "5°, 7° y 9°". */
    public static function label(array $semesters): string
    {
        $items = array_map(fn ($semester) => "{$semester}°", $semesters);
        $last = array_pop($items);

        return $items ? implode(', ', $items)." y {$last}" : (string) $last;
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

    /** Crea los grupos que falten de los semestres de alumnos nuevos que existen en el periodo. */
    public static function ensure(int $career, int $periodId): void
    {
        $period = DB::table('periodos_academicos')->find($periodId);
        $semesters = $period ? self::activeSemesters($career, $period) : self::semesters($career);
        foreach ($semesters as $semester) {
            foreach (self::letters($career) as $letter) {
                $exists = DB::table('grupos_academicos')->where(['carrera_id' => $career, 'periodo_id' => $periodId, 'semestre' => $semester, 'clave_grupo' => $letter])->exists();
                if (!$exists) {
                    DB::table('grupos_academicos')->insert(['carrera_id' => $career, 'periodo_id' => $periodId, 'nombre' => "{$semester}° {$letter}", 'semestre' => $semester,
                        'clave_grupo' => $letter, 'activo' => true, 'creado_en' => now(), 'actualizado_en' => now()]);
                }
            }
        }
    }

    private static function startsInFirstHalf(object $period): bool
    {
        $start = $period->fecha_inicio instanceof \DateTimeInterface ? $period->fecha_inicio : Carbon::parse($period->fecha_inicio);

        return (int) $start->format('n') <= 6;
    }
}
