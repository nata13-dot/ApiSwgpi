<?php

namespace App\Support;

/**
 * Formato de nombre propio para nombres y apellidos: «JOSÉ DE LA ROSA» / «josé de la rosa» → «José de la Rosa».
 * Cada palabra con mayúscula inicial; las partículas (de, del, la, los, y…) en minúscula salvo al inicio;
 * también se respetan los nombres compuestos con guion o apóstrofo y se quitan espacios de más.
 */
final class PersonName
{
    private const PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'das', 'do', 'dos', 'di', 'van', 'von'];

    public static function format(?string $value): ?string
    {
        if ($value === null) return null;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($value === '') return $value;

        $words = explode(' ', mb_strtolower($value, 'UTF-8'));
        foreach ($words as $index => $word) {
            if ($index > 0 && in_array($word, self::PARTICLES, true)) continue;
            $words[$index] = preg_replace_callback('/(^|[-\'’])(\p{L})/u', fn ($m) => $m[1].mb_strtoupper($m[2], 'UTF-8'), $word) ?? $word;
        }

        return implode(' ', $words);
    }
}
