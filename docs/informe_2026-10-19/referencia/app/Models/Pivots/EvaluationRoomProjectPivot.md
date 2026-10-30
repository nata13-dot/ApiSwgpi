# EvaluationRoomProjectPivot

Fuente: [app/Models/Pivots/EvaluationRoomProjectPivot.php](../../../../../../app/Models/Pivots/EvaluationRoomProjectPivot.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EvaluationRoomProjectPivot extends Pivot
{


}
```

## getPresentationOrderAttribute

Visibilidad: `public`. Líneas originales 9–12.

```php
    public function getPresentationOrderAttribute(): int
    {
        return (int) ($this->orden ?? 0);
    }
```

## setPresentationOrderAttribute

Visibilidad: `public`. Líneas originales 14–17.

```php
    public function setPresentationOrderAttribute(int $value): void
    {
        $this->attributes['orden'] = $value;
    }
```

## getStatusAttribute

Visibilidad: `public`. Líneas originales 19–22.

```php
    public function getStatusAttribute(): string
    {
        return 'pendiente';
    }
```
