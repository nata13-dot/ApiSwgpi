# ProjectMemberPivot

Fuente: [app/Models/Pivots/ProjectMemberPivot.php](../../../../../../app/Models/Pivots/ProjectMemberPivot.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectMemberPivot extends Pivot
{
    protected $appends = [
        'rol_asesor',
    ];


}
```

## getRolAsesorAttribute

Visibilidad: `public`. Líneas originales 13–16.

```php
    public function getRolAsesorAttribute(): ?string
    {
        return ($this->rol ?? null) === 'integrante' ? null : $this->rol;
    }
```

## setRolAsesorAttribute

Visibilidad: `public`. Líneas originales 18–21.

```php
    public function setRolAsesorAttribute(?string $value): void
    {
        $this->attributes['rol'] = $value ?: 'integrante';
    }
```
