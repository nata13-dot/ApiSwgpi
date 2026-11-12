<?php

namespace App\Support;

use App\Models\CareerSetting;

/**
 * Formato del número de control y del correo institucional de los alumnos. El número tiene 8 dígitos:
 * generación (2) + clave de la carrera (2) + consecutivo de la generación (4), y puede llevar una b o una c al inicio.
 * El correo siempre es «l» seguida del número completo: AA24NNNN → lAA24NNNN@dominio, bAA24NNNN → lbAA24NNNN@dominio,
 * cAA24NNNN → lcAA24NNNN@dominio.
 * La administración general ajusta por carrera la clave y el dominio.
 */
final class SelfRegistrationRule
{
    public const EMAIL_PREFIX = 'l';
    public const DIGITS = 8;
    /** Letras con las que puede iniciar el número de control (y que pasan al correo tras la «l»). */
    public const LETTERS = ['b', 'c'];

    public const DEFAULT = [
        'career_code' => '',
        'domain' => 'smartin.tecnm.mx',
    ];

    public static function forCareer(int $careerId): array
    {
        return self::normalize((array) CareerSetting::valueForCareer($careerId, 'self_registration_rule', []));
    }

    public static function normalize(array $rule): array
    {
        $rule = array_replace(self::DEFAULT, array_intersect_key($rule, self::DEFAULT));

        return [
            'email_prefix' => self::EMAIL_PREFIX,
            'allow_b' => true,
            'letters' => self::LETTERS,
            'digits' => self::DIGITS,
            'career_code' => substr(preg_replace('/\D/', '', (string) $rule['career_code']), 0, 2),
            'domain' => strtolower(ltrim(trim((string) $rule['domain']), '@')),
        ];
    }

    /** Número de control en minúsculas (la b o la c se guardan en minúscula). */
    public static function normalizeId(string $id): string
    {
        return strtolower(trim($id));
    }

    /** @return string|null mensaje de error, o null si el número de control cumple el formato */
    public static function numberViolation(array $rule, string $id): ?string
    {
        if (!preg_match('/^[' . implode('', self::LETTERS) . ']?(\d{2})(\d{2})(\d{4})$/', self::normalizeId($id), $parts)) {
            return 'El número de control debe tener ' . self::describeNumber($rule) . '.';
        }
        [, $generation, $career, $sequence] = $parts;
        if ((int) $generation > (int) now()->format('y')) {
            return "La generación {$generation} del número de control todavía no existe: revisa los dos primeros dígitos.";
        }
        if ($rule['career_code'] !== '' && $career !== $rule['career_code']) {
            return "El tercer y cuarto dígito del número de control deben ser {$rule['career_code']}, la clave de la carrera.";
        }
        if ($sequence === '0000') {
            return 'Los últimos cuatro dígitos del número de control no pueden ser 0000.';
        }

        return null;
    }

    /** @return string|null mensaje de error, o null si el número de control y el correo cumplen la regla */
    public static function violation(array $rule, string $id, string $email): ?string
    {
        if ($violation = self::numberViolation($rule, $id)) {
            return $violation;
        }
        $id = self::normalizeId($id);
        if (strtolower(trim($email)) !== $rule['email_prefix'] . $id . '@' . $rule['domain']) {
            return 'El correo institucional debe ser ' . $rule['email_prefix'] . $id . '@' . $rule['domain'] . ' (los mismos números que tu número de control).';
        }

        return null;
    }

    public static function describeNumber(array $rule): string
    {
        $career = $rule['career_code'] !== '' ? "la clave de la carrera ({$rule['career_code']})" : '2 de la carrera';

        return "8 dígitos: 2 de la generación, {$career} y 4 del consecutivo (puede llevar b o c al inicio)";
    }

    /** Sólo el formato, con letras: un número de ejemplo podría ser el de una cuenta real. */
    public static function example(array $rule, bool|string $letter = false): string
    {
        $career = $rule['career_code'] !== '' ? $rule['career_code'] : 'CC';
        $letter = $letter === true ? 'b' : ($letter ?: '');

        return $rule['email_prefix'] . $letter . "AA{$career}NNNN@" . $rule['domain'];
    }

    /** Lo que ven los formularios para guiar al alumno. */
    public static function publicPayload(array $rule): array
    {
        return $rule + [
            'example' => self::example($rule),
            'example_b' => self::example($rule, 'b'),
            'example_c' => self::example($rule, 'c'),
            'description' => 'Número de control de ' . self::describeNumber($rule) . '.',
        ];
    }
}
