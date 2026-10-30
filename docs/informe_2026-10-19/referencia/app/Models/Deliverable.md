# Deliverable

Fuente: [app/Models/Deliverable.php](../../../../../app/Models/Deliverable.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deliverable extends Model
{
    use BelongsToCareer, HasFactory, HasLegacyAliases;

    protected $table = 'entregables';

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = null;

    protected array $legacyAliases = [
        'created_at' => 'creado_en',
    ];

    protected $fillable = [
        'carrera_id', 'curso_id', 'nombre', 'descripcion', 'tipo_documento', 'fecha_limite', 'estado', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    // RELACIONES




    // SCOPES


}
```

## course

Visibilidad: `public`. Líneas originales 37–40.

```php
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'curso_id');
    }
```

## deliveries

Visibilidad: `public`. Líneas originales 42–45.

```php
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'entregable_id');
    }
```

## projects

Visibilidad: `public`. Líneas originales 47–51.

```php
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'entregas', 'entregable_id', 'proyecto_id')
            ->withPivot(['id', 'documento_id', 'enviado_por', 'entregado_en', 'calificacion', 'comentarios_docente']);
    }
```

## feedbacks

Visibilidad: `public`. Líneas originales 53–56.

```php
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'deliverable_id');
    }
```

## scopeActivos

Visibilidad: `public`. Líneas originales 59–62.

```php
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
```

## scopeEstado

Visibilidad: `public`. Líneas originales 64–67.

```php
    public function scopeEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }
```

## scopeByProject

Visibilidad: `public`. Líneas originales 69–72.

```php
    public function scopeByProject($query, $projectId)
    {
        return $query->whereHas('deliveries', fn ($delivery) => $delivery->where('proyecto_id', $projectId));
    }
```
