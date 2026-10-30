# UserCareer

Fuente: [app/Models/UserCareer.php](../../../../../app/Models/UserCareer.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCareer extends Model
{

    protected $table = 'usuario_carrera';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'carrera_id',
        'perfil_id',
        'es_principal',
        'activo',
        'asignado_por',
    ];

    protected $casts = [
        'carrera_id' => 'integer',
        'perfil_id' => 'integer',
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];


}
```

## booted

Visibilidad: `protected`. Líneas originales 10–28.

```php
    protected static function booted(): void
    {
        static::updating(function (self $membership) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('residencias')) return;
            if ($membership->isDirty('perfil_id') || ($membership->isDirty('activo') && !$membership->activo)) {
                app(\App\Services\AcademicMembershipService::class)->ensureReleased($membership);
            }
        });
        static::updated(function (self $membership) {
            if (!\Illuminate\Support\Facades\Schema::hasTable('bitacora_academica')) return;
            app(\App\Services\AcademicWorkflowService::class)->audit('membresia.cambio', 'membresia', $membership->id, $membership->getOriginal(), $membership->getAttributes());
        });
        static::created(function (self $membership) {
            if (\Illuminate\Support\Facades\Schema::hasTable('bitacora_academica')) app(\App\Services\AcademicWorkflowService::class)->audit('membresia.alta', 'membresia', $membership->id, null, $membership->getAttributes());
        });
        static::deleting(function (self $membership) {
            if (\Illuminate\Support\Facades\Schema::hasTable('bitacora_academica')) abort(409, 'Desactiva la membresía para conservar el historial.');
        });
    }
```

## user

Visibilidad: `public`. Líneas originales 51–54.

```php
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
```

## career

Visibilidad: `public`. Líneas originales 56–59.

```php
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'carrera_id');
    }
```
