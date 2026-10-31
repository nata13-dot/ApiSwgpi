<?php

namespace App\Support;

use App\Models\CareerSetting;

/**
 * Formato del número de control y del correo institucional que una carrera acepta en el autoregistro.
 * El correo es el prefijo seguido del número de control completo: 22240022 → l22240022@smartin.tecnm.mx,
 * b20240087 → lb20240087@smartin.tecnm.mx. La administración general ajusta la regla de cada carrera.
 */
final class SelfRegistrationRule
{
    public const DEFAULT = [
        'email_prefix' => 'l',
        'allow_b' => true,
        'number_prefix' => '',
        'digits' => 8,
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
            'email_prefix' => strtolower(trim((string) $rule['email_prefix'])),
            'allow_b' => (bool) $rule['allow_b'],
            'number_prefix' => preg_replace('/\D/', '', (string) $rule['number_prefix']),
            'digits' => max(4, min(12, (int) $rule['digits'])),
            'domain' => strtolower(ltrim(trim((string) $rule['domain']), '@')),
        ];
    }

    /** Número de control en minúsculas (la b se guarda como "b"). */
    public static function normalizeId(string $id): string
    {
        return strtolower(trim($id));
    }

    /** @return string|null mensaje de error, o null si el número de control y el correo cumplen la regla */
    public static function violation(array $rule, string $id, string $email): ?string
    {
        $id = self::normalizeId($id);
        $email = strtolower(trim($email));
        $pattern = '/^' . ($rule['allow_b'] ? 'b?' : '') . preg_quote($rule['number_prefix'], '/')
            . '\d{' . max(0, $rule['digits'] - strlen($rule['number_prefix'])) . '}$/';

        if (!preg_match($pattern, $id)) {
            return 'El número de control debe tener ' . self::describeNumber($rule) . '.';
        }
        if ($email !== $rule['email_prefix'] . $id . '@' . $rule['domain']) {
            return 'El correo institucional debe ser ' . $rule['email_prefix'] . $id . '@' . $rule['domain'] . ' (los mismos números que tu número de control).';
        }

        return null;
    }

    public static function describeNumber(array $rule): string
    {
        $text = "{$rule['digits']} dígitos";
        if ($rule['number_prefix'] !== '') $text .= " que empiezan con {$rule['number_prefix']}";
        if ($rule['allow_b']) $text .= ' (puede llevar b al inicio)';

        return $text;
    }

    public static function example(array $rule): string
    {
        $number = str_pad($rule['number_prefix'], $rule['digits'], '0');
        $number = substr($number, 0, $rule['digits'] - 2) . '22';

        return $rule['email_prefix'] . $number . '@' . $rule['domain'];
    }

    /** Lo que ven los formularios para guiar al alumno. */
    public static function publicPayload(array $rule): array
    {
        return $rule + ['example' => self::example($rule), 'description' => 'Número de control de ' . self::describeNumber($rule) . '.'];
    }
}
