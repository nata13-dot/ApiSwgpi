# AcademicPeriod

Fuente: [app/Support/AcademicPeriod.php](../../../../../app/Support/AcademicPeriod.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class AcademicPeriod
{




}
```

## normalize

Visibilidad: `public`. Líneas originales 9–20.

```php
    public static function normalize(string $period): string
    {
        $period = trim($period);

        if (!preg_match('/^(20\d{2})-([12])$/', $period, $matches)) {
            throw ValidationException::withMessages([
                'active_academic_period' => ['El periodo debe usar el formato AAAA-1 o AAAA-2.'],
            ]);
        }

        return "{$matches[1]}-{$matches[2]}";
    }
```

## semesters

Visibilidad: `public`. Líneas originales 22–27.

```php
    public static function semesters(string $period): array
    {
        $normalized = self::normalize($period);

        return str_ends_with($normalized, '-1') ? [6, 8] : [5, 7, 9];
    }
```

## information

Visibilidad: `public`. Líneas originales 29–43.

```php
    public static function information(string $period): array
    {
        $normalized = self::normalize($period);
        [$year, $half] = explode('-', $normalized);

        return [
            'code' => $normalized,
            'year' => (int) $year,
            'half' => (int) $half,
            'label' => $half === '1'
                ? "Primera mitad de {$year}"
                : "Segunda mitad de {$year}",
            'semesters' => self::semesters($normalized),
        ];
    }
```

## options

Visibilidad: `public`. Líneas originales 45–57.

```php
    public static function options(?int $centerYear = null): array
    {
        $centerYear ??= (int) now()->format('Y');
        $options = [];

        for ($year = $centerYear - 1; $year <= $centerYear + 1; $year++) {
            foreach ([1, 2] as $half) {
                $options[] = self::information("{$year}-{$half}");
            }
        }

        return $options;
    }
```

## next

Visibilidad: `public`. Líneas originales 59–65.

```php
    public static function next(string $period): string
    {
        $normalized = self::normalize($period);
        [$year, $half] = array_map('intval', explode('-', $normalized));

        return $half === 1 ? "{$year}-2" : ($year + 1).'-1';
    }
```
