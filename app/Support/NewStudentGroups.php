<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\CareerSetting;
use Illuminate\Support\Facades\DB;

/**
 * Los alumnos nuevos entran a 5° semestre, en uno de los grupos que define la carrera (por defecto A, B y C).
 * Como 5° es impar, el registro de alumnos nuevos sólo existe de julio a diciembre.
 */
final class NewStudentGroups
{
    public const SEMESTER = 5;
    public const DEFAULT = ['A', 'B', 'C'];

    /** @return string[] */
    public static function letters(int $career): array
    {
        return array_values((array) CareerSetting::valueForCareer($career, 'new_student_groups', self::DEFAULT)) ?: self::DEFAULT;
    }

    public static function allowedIn(object $period): bool
    {
        $start = $period->fecha_inicio instanceof \DateTimeInterface ? $period->fecha_inicio : \Illuminate\Support\Carbon::parse($period->fecha_inicio);

        return (int) $start->format('n') > 6;
    }

    /** Crea los grupos de 5° que falten en el periodo. */
    public static function ensure(int $career, int $periodId): void
    {
        foreach (self::letters($career) as $letter) {
            $exists = DB::table('grupos_academicos')->where(['carrera_id' => $career, 'periodo_id' => $periodId, 'semestre' => self::SEMESTER, 'clave_grupo' => $letter])->exists();
            if (!$exists) {
                DB::table('grupos_academicos')->insert(['carrera_id' => $career, 'periodo_id' => $periodId, 'nombre' => self::SEMESTER."° {$letter}", 'semestre' => self::SEMESTER,
                    'clave_grupo' => $letter, 'activo' => true, 'creado_en' => now(), 'actualizado_en' => now()]);
            }
        }
    }
}
