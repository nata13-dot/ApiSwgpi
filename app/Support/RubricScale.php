<?php

namespace App\Support;

/**
 * Escalas de las preguntas de rúbrica y su conversión a porcentaje.
 * La respuesta más baja vale 0 % y la más alta 100 % en cualquier escala, así una rúbrica puede mezclar
 * acuerdos, 1–5 y 1–10 y el promedio sigue siendo justo: cada pregunta pesa lo mismo.
 * Resultado: 80 o más aprobado, de 70 a 79.99 apenas cumple, menos de 70 reprobado.
 */
final class RubricScale
{
    public const AGREEMENT = 'acuerdo';
    public const POINTS_5 = 'puntos_5';
    public const POINTS_10 = 'puntos_10';

    public const NAMES = [
        self::AGREEMENT => 'Nivel de acuerdo',
        self::POINTS_5 => 'Puntaje del 1 al 5',
        self::POINTS_10 => 'Puntaje del 1 al 10',
    ];

    /** Opciones de la escala de acuerdo, de menor a mayor (valor guardado => [puntos, etiqueta]). */
    public const AGREEMENT_OPTIONS = [
        'completamente_en_desacuerdo' => [1, 'Completamente en desacuerdo'],
        'en_desacuerdo' => [2, 'En desacuerdo'],
        'nulo' => [3, 'Nulo'],
        'de_acuerdo' => [4, 'De acuerdo'],
        'completamente_de_acuerdo' => [5, 'Completamente de acuerdo'],
    ];

    /** Nombres que usaban las rúbricas anteriores para la misma escala. */
    private const AGREEMENT_ALIASES = [
        'totalmente_en_desacuerdo' => 'completamente_en_desacuerdo',
        'neutral' => 'nulo',
        'totalmente_de_acuerdo' => 'completamente_de_acuerdo',
    ];

    public const APPROVED = 80;
    public const BARELY = 70;

    public static function isValid(string $scale): bool
    {
        return isset(self::NAMES[$scale]);
    }

    /** @return array<int, array{value: string, points: int, label: string}> */
    public static function options(string $scale): array
    {
        if ($scale === self::AGREEMENT) {
            return array_map(fn ($key) => ['value' => $key, 'points' => self::AGREEMENT_OPTIONS[$key][0], 'label' => self::AGREEMENT_OPTIONS[$key][1]], array_keys(self::AGREEMENT_OPTIONS));
        }
        $max = $scale === self::POINTS_10 ? 10 : 5;

        return array_map(fn ($points) => ['value' => (string) $points, 'points' => $points, 'label' => (string) $points], range(1, $max));
    }

    /** Puntos de una respuesta, o null si no pertenece a la escala. */
    public static function points(string $scale, string $value): ?int
    {
        if ($scale === self::AGREEMENT) {
            $value = self::AGREEMENT_ALIASES[$value] ?? $value;

            return self::AGREEMENT_OPTIONS[$value][0] ?? null;
        }
        $max = $scale === self::POINTS_10 ? 10 : ($scale === self::POINTS_5 ? 5 : 0);
        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > $max) {
            return null;
        }

        return (int) $value;
    }

    public static function normalizeValue(string $scale, string $value): string
    {
        return $scale === self::AGREEMENT ? (self::AGREEMENT_ALIASES[$value] ?? $value) : $value;
    }

    /** Porcentaje del mínimo al máximo: 1 → 0 %, máximo → 100 %. */
    public static function percentage(string $scale, int $points): float
    {
        $max = $scale === self::POINTS_10 ? 10 : 5;

        return round(($points - 1) / ($max - 1) * 100, 2);
    }

    public static function label(string $scale, ?string $value): string
    {
        if ($value === null) return '-';
        if ($scale === self::AGREEMENT) {
            $value = self::AGREEMENT_ALIASES[$value] ?? $value;

            return self::AGREEMENT_OPTIONS[$value][1] ?? $value;
        }

        return $value . ' de ' . ($scale === self::POINTS_10 ? 10 : 5);
    }

    /** @return array{key: string, label: string, tone: string} */
    public static function verdict(?float $average): array
    {
        if ($average === null) {
            return ['key' => 'sin_resultado', 'label' => 'Sin resultado', 'tone' => 'secondary'];
        }
        if ($average >= self::APPROVED) {
            return ['key' => 'aprobado', 'label' => 'Aprobado', 'tone' => 'success'];
        }
        if ($average >= self::BARELY) {
            return ['key' => 'apenas_cumple', 'label' => 'Apenas cumple', 'tone' => 'warning'];
        }

        return ['key' => 'reprobado', 'label' => 'Reprobado', 'tone' => 'danger'];
    }
}
