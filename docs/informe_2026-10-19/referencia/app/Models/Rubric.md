# Rubric

Fuente: [app/Models/Rubric.php](../../../../../app/Models/Rubric.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    use BelongsToCareer;

    protected $table = 'rubricas';
    public $timestamps = false;

    protected $fillable = ['carrera_id', 'nombre', 'etapa', 'semestre', 'activa'];

    protected $casts = [
        'semestre' => 'integer',
        'activa' => 'boolean',
    ];

}
```

## rooms

Visibilidad: `public`. Líneas originales 23–26.

```php
    public function rooms(): HasMany
    {
        return $this->hasMany(EvaluationRoom::class, 'rubrica_id');
    }
```
