# HasLegacyAliases

Fuente: [app/Models/Concerns/HasLegacyAliases.php](../../../../../../app/Models/Concerns/HasLegacyAliases.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Concerns;

use App\Models\Concerns\LegacyAliasBuilder;
use Illuminate\Database\Eloquent\Builder;

trait HasLegacyAliases
{





}
```

## newEloquentBuilder

Visibilidad: `public`. Líneas originales 10–13.

```php
    public function newEloquentBuilder($query): Builder
    {
        return new LegacyAliasBuilder($query);
    }
```

## getAttribute

Visibilidad: `public`. Líneas originales 15–22.

```php
    public function getAttribute($key)
    {
        if (isset($this->legacyAliases[$key])) {
            return parent::getAttribute($this->legacyAliases[$key]);
        }

        return parent::getAttribute($key);
    }
```

## setAttribute

Visibilidad: `public`. Líneas originales 24–31.

```php
    public function setAttribute($key, $value)
    {
        if (isset($this->legacyAliases[$key])) {
            $key = $this->legacyAliases[$key];
        }

        return parent::setAttribute($key, $value);
    }
```

## scopeWhereLegacy

Visibilidad: `public`. Líneas originales 33–42.

```php
    public function scopeWhereLegacy($query, string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
    {
        $column = $this->legacyAliases[$column] ?? $column;

        if (func_num_args() === 3) {
            return $query->where($column, $operator, null, $boolean);
        }

        return $query->where($column, $operator, $value, $boolean);
    }
```

## getLegacyAliases

Visibilidad: `public`. Líneas originales 44–47.

```php
    public function getLegacyAliases(): array
    {
        return $this->legacyAliases ?? [];
    }
```

## getLegacyVirtualColumns

Visibilidad: `public`. Líneas originales 49–52.

```php
    public function getLegacyVirtualColumns(): array
    {
        return $this->legacyVirtualColumns ?? [];
    }
```
