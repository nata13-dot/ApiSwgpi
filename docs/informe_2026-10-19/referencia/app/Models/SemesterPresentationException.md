# SemesterPresentationException

Fuente: [app/Models/SemesterPresentationException.php](../../../../../app/Models/SemesterPresentationException.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterPresentationException extends Model
{

    protected $table = 'autorizaciones_excepcionales';
    public $timestamps = false;

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'tipo',
        'valor',
        'motivo',
        'vigente_hasta',
        'autorizada_por',
        'activa',
    ];

    protected $appends = [
        'periodo_id',
        'semestre_presentacion',
        'activo',
    ];

    protected $casts = [
        'proyecto_id' => 'integer',
        'activa' => 'boolean',
        'vigente_hasta' => 'datetime',
        'creada_en' => 'datetime',
    ];








}
```

## booted

Visibilidad: `protected`. Líneas originales 10–15.

```php
    protected static function booted(): void
    {
        static::addGlobalScope('career', function ($query) {
            $query->whereHas('project');
        });
    }
```

## period

Visibilidad: `public`. Líneas originales 44–47.

```php
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }
```

## project

Visibilidad: `public`. Líneas originales 49–52.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }
```

## student

Visibilidad: `public`. Líneas originales 54–57.

```php
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
```

## getPeriodoIdAttribute

Visibilidad: `public`. Líneas originales 59–64.

```php
    public function getPeriodoIdAttribute(): ?int
    {
        $value = $this->decodedValue()['period_id'] ?? null;

        return $value === null ? null : (int) $value;
    }
```

## getSemestrePresentacionAttribute

Visibilidad: `public`. Líneas originales 66–71.

```php
    public function getSemestrePresentacionAttribute(): ?int
    {
        $value = $this->decodedValue()['semester'] ?? null;

        return $value === null ? null : (int) $value;
    }
```

## getActivoAttribute

Visibilidad: `public`. Líneas originales 73–76.

```php
    public function getActivoAttribute(): bool
    {
        return (bool) $this->activa;
    }
```

## encodedValue

Visibilidad: `public`. Líneas originales 78–84.

```php
    public static function encodedValue(int $periodId, int $semester): string
    {
        return json_encode([
            'period_id' => $periodId,
            'semester' => $semester,
        ], JSON_UNESCAPED_UNICODE);
    }
```

## decodedValue

Visibilidad: `private`. Líneas originales 86–96.

```php
    private function decodedValue(): array
    {
        $decoded = json_decode((string) $this->valor, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return is_numeric($this->valor)
            ? ['semester' => (int) $this->valor]
            : [];
    }
```
