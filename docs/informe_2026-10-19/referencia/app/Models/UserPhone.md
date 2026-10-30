# UserPhone

Fuente: [app/Models/UserPhone.php](../../../../../app/Models/UserPhone.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhone extends Model
{
    protected $table = 'usuarios_telefonos';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'telefono',
        'tipo',
    ];

}
```

## user

Visibilidad: `public`. Líneas originales 19–22.

```php
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
```
