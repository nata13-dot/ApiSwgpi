# EvaluationScore

Fuente: [app/Models/EvaluationScore.php](../../../../../app/Models/EvaluationScore.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class EvaluationScore extends Model
{
    use HasLegacyAliases;

    protected $table = 'respuestas_evaluacion';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'nivel' => 'valor',
        'rubric_criterion_id' => 'criterio_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['evaluation_id', 'teacher_id', 'criterio'];

    protected $appends = [
        'evaluation_id',
        'teacher_id',
        'criterio',
        'nivel',
    ];

    protected $fillable = [
        'dictamen_id', 'criterio_id', 'valor', 'nivel', 'puntaje', 'comentario',
    ];

    protected $casts = [
        'puntaje' => 'integer',
    ];








}
```

## evaluation

Visibilidad: `public`. Líneas originales 42–45.

```php
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'dictamen_id');
    }
```

## teacher

Visibilidad: `public`. Líneas originales 47–57.

```php
    public function teacher(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            EvaluationAttempt::class,
            'id',
            'id',
            'dictamen_id',
            'docente_id'
        )->withoutGlobalScope('careerMembership');
    }
```

## attempt

Visibilidad: `public`. Líneas originales 59–62.

```php
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'dictamen_id');
    }
```

## getEvaluationIdAttribute

Visibilidad: `public`. Líneas originales 64–67.

```php
    public function getEvaluationIdAttribute(): ?int
    {
        return $this->attempt?->evaluacion_id;
    }
```

## getTeacherIdAttribute

Visibilidad: `public`. Líneas originales 69–76.

```php
    public function getTeacherIdAttribute(): ?string
    {
        if ($this->relationLoaded('teacher')) {
            return $this->teacher?->id;
        }

        return $this->attempt?->docente_id;
    }
```

## getCriterioAttribute

Visibilidad: `public`. Líneas originales 78–85.

```php
    public function getCriterioAttribute(): ?string
    {
        if ($this->relationLoaded('criterion')) {
            return $this->criterion?->clave;
        }

        return $this->criterion?->clave;
    }
```

## getNivelAttribute

Visibilidad: `public`. Líneas originales 87–90.

```php
    public function getNivelAttribute(): ?string
    {
        return $this->valor;
    }
```

## criterion

Visibilidad: `public`. Líneas originales 92–95.

```php
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'criterio_id');
    }
```
