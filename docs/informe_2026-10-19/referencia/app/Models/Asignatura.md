# Asignatura

Fuente: [app/Models/Asignatura.php](../../../../../app/Models/Asignatura.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asignatura extends Model
{
    use HasFactory, BelongsToCareer;

    protected $table = 'asignaturas';
    public $timestamps = false;

    protected $fillable = ['carrera_id', 'clave', 'nombre', 'descripcion', 'activo'];


    // RELACIONES



    // SCOPES

}
```

## competencias

Visibilidad: `public`. Líneas originales 22–25.

```php
    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class, 'asignatura_id');
    }
```

## projects

Visibilidad: `public`. Líneas originales 27–31.

```php
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'cursos', 'asignatura_id', 'grupo_id', 'id', 'grupo_id')
            ->wherePivot('activo', true);
    }
```

## subjectGroups

Visibilidad: `public`. Líneas originales 33–36.

```php
    public function subjectGroups(): BelongsToMany
    {
        return $this->belongsToMany(SubjectGroup::class, 'cursos', 'asignatura_id', 'grupo_id');
    }
```
