# Empresa

Fuente: [app/Models/Empresa.php](../../../../../app/Models/Empresa.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';
    const CREATED_AT = 'creada_en';
    const UPDATED_AT = 'actualizada_en';

    protected $fillable = [
        'nombre',
        'rfc',
        'giro',
        'contacto_nombre',
        'contacto_cargo',
        'direccion',
        'estado_validacion', 'solicitada_por', 'validada_por', 'comentario_validacion', 'validada_en',
    ];

    protected $casts = ['validada_en' => 'datetime', 'creada_en' => 'datetime', 'actualizada_en' => 'datetime'];

}
```

## projects

Visibilidad: `public`. Líneas originales 26–29.

```php
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'empresa_id');
    }
```
