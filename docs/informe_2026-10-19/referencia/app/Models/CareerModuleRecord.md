# CareerModuleRecord

Fuente: [app/Models/CareerModuleRecord.php](../../../../../app/Models/CareerModuleRecord.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerModuleRecord extends Model
{
    use BelongsToCareer;

    protected $table = 'registros_modulo_carrera';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'carrera_id',
        'modulo',
        'clave',
        'titulo',
        'descripcion',
        'estado',
        'responsable_id',
        'fecha_inicio',
        'fecha_fin',
        'datos',
        'activo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'datos' => 'array',
        'activo' => 'boolean',
    ];

}
```

## responsible

Visibilidad: `public`. Líneas originales 38–41.

```php
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
```
