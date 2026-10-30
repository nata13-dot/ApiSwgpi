# Feedback

Fuente: [app/Models/Feedback.php](../../../../../app/Models/Feedback.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';
    public $timestamps = false;

    protected $fillable = ['deliverable_id', 'comentario', 'comentado_por', 'estado'];

    // RELACIONES


    // SCOPES
}
```

## deliverable

Visibilidad: `public`. Líneas originales 19–22.

```php
    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'deliverable_id');
    }
```

## commentedBy

Visibilidad: `public`. Líneas originales 24–27.

```php
    public function commentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comentado_por', 'id')->where('activo', true);
    }
```

## scopePendiente

Visibilidad: `public`. Líneas originales 30–30.

```php
    public function scopePendiente($query) { return $query->where('estado', 'pendiente'); }
```

## scopeRevisado

Visibilidad: `public`. Líneas originales 31–31.

```php
    public function scopeRevisado($query) { return $query->where('estado', 'revisado'); }
```
