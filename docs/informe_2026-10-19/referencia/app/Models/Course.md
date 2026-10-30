# Course

Fuente: [app/Models/Course.php](../../../../../app/Models/Course.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToCareer;

    protected $table = 'cursos';

    public $timestamps = false;

    protected $fillable = ['carrera_id', 'grupo_id', 'asignatura_id', 'activo', 'es_seguimiento_proyecto', 'clave_autoregistro', 'clave_actualizada_en', 'clave_actualizada_por', 'inscripcion_desde', 'inscripcion_hasta'];

    protected $hidden = ['clave_autoregistro'];

    protected $casts = ['inscripcion_desde' => 'datetime', 'inscripcion_hasta' => 'datetime', 'activo' => 'boolean', 'es_seguimiento_proyecto' => 'boolean', 'clave_actualizada_en' => 'datetime'];




}
```

## subject

Visibilidad: `public`. Líneas originales 25–28.

```php
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id');
    }
```

## group

Visibilidad: `public`. Líneas originales 30–33.

```php
    public function group(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_id');
    }
```

## students

Visibilidad: `public`. Líneas originales 35–39.

```php
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'curso_estudiantes', 'curso_id', 'estudiante_id')
            ->withPivot(['inscrito_en', 'activo']);
    }
```

## deliverables

Visibilidad: `public`. Líneas originales 41–44.

```php
    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'curso_id');
    }
```
