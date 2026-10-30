# Grafo

Fuente: [app/Models/Grafo.php](../../../../../app/Models/Grafo.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grafo extends Model
{
    use HasFactory;

    protected $table = 'grafos';
    public $timestamps = false;

    protected $fillable = ['project_id', 'datos', 'descripcion'];

    // RELACIONES
}
```

## project

Visibilidad: `public`. Líneas originales 19–22.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
```
