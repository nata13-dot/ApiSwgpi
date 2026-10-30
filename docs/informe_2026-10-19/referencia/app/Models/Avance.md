# Avance

Fuente: [app/Models/Avance.php](../../../../../app/Models/Avance.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avance extends Model
{
    use HasFactory;

    protected $table = 'avances_proyecto';
    public $timestamps = false;

    protected $fillable = ['project_id', 'descripcion', 'porcentaje', 'reportado_por'];

    protected $casts = [
        'porcentaje' => 'decimal:2',
    ];

    // RELACIONES

}
```

## project

Visibilidad: `public`. Líneas originales 23–26.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
```

## reportedBy

Visibilidad: `public`. Líneas originales 28–31.

```php
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por', 'id')->where('activo', true);
    }
```
