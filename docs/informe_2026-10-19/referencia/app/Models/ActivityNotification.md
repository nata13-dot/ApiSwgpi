# ActivityNotification

Fuente: [app/Models/ActivityNotification.php](../../../../../app/Models/ActivityNotification.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityNotification extends Model
{
    use BelongsToCareer;

    protected $table = 'notificaciones';
    const CREATED_AT = 'creada_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'carrera_id',
        'usuario_id',
        'actor_id',
        'tipo',
        'titulo',
        'mensaje',
        'url',
        'leida_en',
    ];

    protected $casts = [
        'leida_en' => 'datetime',
        'creada_en' => 'datetime',
    ];


}
```

## user

Visibilidad: `public`. Líneas originales 33–36.

```php
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
```

## actor

Visibilidad: `public`. Líneas originales 38–41.

```php
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
```
