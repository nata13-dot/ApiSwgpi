# BelongsToCareer

Fuente: [app/Models/Concerns/BelongsToCareer.php](../../../../../../app/Models/Concerns/BelongsToCareer.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Concerns;

use App\Models\Career;
use App\Support\CareerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCareer
{


}
```

## bootBelongsToCareer

Visibilidad: `protected`. Líneas originales 12–26.

```php
    protected static function bootBelongsToCareer(): void
    {
        static::addGlobalScope('career', function (Builder $query): void {
            $careerId = app(CareerContext::class)->careerId();
            if ($careerId) {
                $query->where($query->getModel()->qualifyColumn('carrera_id'), $careerId);
            }
        });

        static::creating(function ($model): void {
            if (!$model->carrera_id) {
                $model->carrera_id = app(CareerContext::class)->careerId();
            }
        });
    }
```

## career

Visibilidad: `public`. Líneas originales 28–31.

```php
    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'carrera_id');
    }
```

## scopeForCareer

Visibilidad: `public`. Líneas originales 33–39.

```php
    public function scopeForCareer(Builder $query, int $careerId): Builder
    {
        return $query->withoutGlobalScope('career')->where(
            $query->getModel()->qualifyColumn('carrera_id'),
            $careerId
        );
    }
```
