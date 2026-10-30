# Career

Fuente: [app/Models/Career.php](../../../../../app/Models/Career.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Career extends Model
{
    protected $table = 'carreras';

    const CREATED_AT = 'creada_en';
    const UPDATED_AT = 'actualizada_en';

    protected $fillable = [
        'clave',
        'slug',
        'nombre',
        'nombre_corto',
        'color_primario',
        'color_secundario',
        'color_acento',
        'lema',
        'logo_ruta',
        'portada_ruta',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];


}
```

## users

Visibilidad: `public`. Líneas originales 34–39.

```php
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_carrera', 'carrera_id', 'usuario_id')
            ->withPivot(['perfil_id', 'es_principal', 'activo', 'asignado_por'])
            ->withTimestamps('creado_en', 'actualizado_en');
    }
```

## memberships

Visibilidad: `public`. Líneas originales 41–44.

```php
    public function memberships(): HasMany
    {
        return $this->hasMany(UserCareer::class, 'carrera_id');
    }
```
