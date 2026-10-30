# Delivery

Fuente: [app/Models/Delivery.php](../../../../../app/Models/Delivery.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $table = 'entregas';

    public $timestamps = false;

    protected $fillable = [
        'entregable_id',
        'proyecto_id',
        'documento_id',
        'enviado_por',
        'entregado_en',
        'calificacion',
        'comentarios_docente',
    ];

    protected $casts = [
        'entregado_en' => 'datetime',
        'calificacion' => 'decimal:2',
    ];




}
```

## deliverable

Visibilidad: `public`. Líneas originales 29–32.

```php
    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'entregable_id');
    }
```

## project

Visibilidad: `public`. Líneas originales 34–37.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }
```

## document

Visibilidad: `public`. Líneas originales 39–42.

```php
    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_id');
    }
```

## submittedBy

Visibilidad: `public`. Líneas originales 44–47.

```php
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por', 'id')->withoutGlobalScope('careerMembership');
    }
```
