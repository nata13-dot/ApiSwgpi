# CareerModule

Fuente: [app/Models/CareerModule.php](../../../../../app/Models/CareerModule.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;

class CareerModule extends Model
{
    use BelongsToCareer;

    protected $table = 'carrera_modulos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['carrera_id', 'modulo', 'habilitado', 'configuracion'];

    protected $casts = [
        'habilitado' => 'boolean',
        'configuracion' => 'array',
    ];
}
```
