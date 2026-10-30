<?php

namespace App\Support;

/**
 * Perfiles del sistema por nivel (1 = más alto). Los id son los de la tabla perfiles y no cambian;
 * el orden de la jerarquía está en perfiles.nivel.
 */
final class Profiles
{
    public const GENERAL_ADMIN = 4;
    public const CAREER_HEAD = 5;
    public const HEAD_ASSISTANT = 6;
    public const PROJECT_COORDINATOR = 7;
    public const TEACHER = 2;
    public const STUDENT = 3;

    /** id => [nivel, nombre visible] */
    public const LEVELS = [
        self::GENERAL_ADMIN => [1, 'Administrador general'],
        self::CAREER_HEAD => [2, 'Jefe de carrera'],
        self::HEAD_ASSISTANT => [3, 'Asistente de jefatura'],
        self::PROJECT_COORDINATOR => [4, 'Coordinador de proyectos integradores'],
        self::TEACHER => [5, 'Docente'],
        self::STUDENT => [6, 'Estudiante'],
    ];

    /** Perfiles que se asignan dentro de una carrera, en orden de nivel (el administrador general es global). */
    public const ASSIGNABLE = [self::CAREER_HEAD, self::HEAD_ASSISTANT, self::PROJECT_COORDINATOR, self::TEACHER, self::STUDENT];

    public static function label(int $profileId): ?string
    {
        return self::LEVELS[$profileId][1] ?? null;
    }

    public static function level(int $profileId): ?int
    {
        return self::LEVELS[$profileId][0] ?? null;
    }

    /** Regla de validación para un perfil asignable a una membresía. */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::ASSIGNABLE);
    }
}
