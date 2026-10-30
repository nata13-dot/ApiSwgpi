# Evaluation

Fuente: [app/Models/Evaluation.php](../../../../../app/Models/Evaluation.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Evaluation extends Model
{
    use HasLegacyAliases, BelongsToCareer;

    protected $table = 'evaluaciones';
    const CREATED_AT = 'creada_en';
    const UPDATED_AT = 'actualizada_en';

    protected array $legacyAliases = [
        'project_id' => 'proyecto_id',
        'evaluation_room_id' => 'sala_id',
        'presentation_order' => 'orden_presentacion',
        'room_feedback' => 'retroalimentacion',
        'feedback_by' => 'retroalimentado_por',
        'feedback_at' => 'retroalimentado_en',
        'finalized_at' => 'finalizada_en',
        'archived_at' => 'archivada_en',
        'archived_by' => 'archivada_por',
        'created_by' => 'creada_por',
        'created_at' => 'creada_en',
        'updated_at' => 'actualizada_en',
    ];

    protected array $legacyVirtualColumns = ['sala', 'semestre', 'etapa', 'sequence_status'];

    protected $fillable = ['criterios_snapshot', 'modo_puntaje', 'autorizacion_id', 'presentacion_autorizacion_id',
        'carrera_id', 'project_id', 'evaluation_room_id', 'semestre', 'etapa', 'sala', 'fecha_exposicion',
        'presentation_order', 'sequence_status', 'estado', 'resultado', 'room_feedback',
        'feedback_by', 'feedback_at', 'finalized_at', 'archived_at', 'archived_by',
        'apto_titulacion', 'created_by',
    ];

    protected $casts = [
        'criterios_snapshot' => 'array',
        'semestre' => 'integer',
        'fecha_exposicion' => 'datetime',
        'retroalimentado_en' => 'datetime',
        'finalizada_en' => 'datetime',
        'archivada_en' => 'datetime',
        'apto_titulacion' => 'boolean',
    ];











}
```

## project

Visibilidad: `public`. Líneas originales 54–57.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }
```

## room

Visibilidad: `public`. Líneas originales 59–62.

```php
    public function room(): BelongsTo
    {
        return $this->belongsTo(EvaluationRoom::class, 'sala_id');
    }
```

## getSalaAttribute

Visibilidad: `public`. Líneas originales 64–67.

```php
    public function getSalaAttribute(): ?string
    {
        return $this->room?->nombre;
    }
```

## getSemestreAttribute

Visibilidad: `public`. Líneas originales 69–78.

```php
    public function getSemestreAttribute(): ?int
    {
        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $semester = $this->room?->rubric?->semestre;

        return $semester === null ? null : (int) $semester;
    }
```

## getEtapaAttribute

Visibilidad: `public`. Líneas originales 80–87.

```php
    public function getEtapaAttribute(): ?string
    {
        if (array_key_exists('etapa', $this->attributes)) {
            return $this->attributes['etapa'];
        }

        return $this->room?->rubric?->etapa;
    }
```

## getSequenceStatusAttribute

Visibilidad: `public`. Líneas originales 89–96.

```php
    public function getSequenceStatusAttribute(): string
    {
        return match ($this->estado) {
            'en_evaluacion' => 'activo',
            'finalizada', 'archivada' => 'evaluado',
            default => 'pendiente',
        };
    }
```

## setSalaAttribute

Visibilidad: `public`. Líneas originales 98–101.

```php
    public function setSalaAttribute(?string $value): void
    {
        // The normalized schema derives the room name through sala_id.
    }
```

## creator

Visibilidad: `public`. Líneas originales 103–106.

```php
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creada_por', 'id')->where('activo', true);
    }
```

## scores

Visibilidad: `public`. Líneas originales 108–118.

```php
    public function scores(): HasManyThrough
    {
        return $this->hasManyThrough(
            EvaluationScore::class,
            EvaluationAttempt::class,
            'evaluacion_id',
            'dictamen_id',
            'id',
            'id'
        );
    }
```

## attempts

Visibilidad: `public`. Líneas originales 120–123.

```php
    public function attempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class, 'evaluacion_id');
    }
```

## getAverageAttribute

Visibilidad: `public`. Líneas originales 125–129.

```php
    public function getAverageAttribute(): float
    {
        $average = $this->scores->avg('puntaje');
        return $average === null ? 0 : round(($average / 4) * 100, 2);
    }
```
