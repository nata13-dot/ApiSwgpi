# SubjectGroup

Fuente: [app/Models/SubjectGroup.php](../../../../../app/Models/SubjectGroup.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectGroup extends Model
{
    use HasFactory, HasLegacyAliases, BelongsToCareer;

    protected $table = 'grupos_academicos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'id',
        'grupo' => 'clave_grupo',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = ['carrera_id', 'nombre', 'semestre', 'grupo', 'periodo_id', 'registro_proyectos_desde', 'registro_proyectos_hasta', 'activo'];

    protected $appends = ['periodo'];

    protected $casts = [
        'semestre' => 'integer',
        'activo' => 'boolean',
        'registro_proyectos_desde' => 'datetime',
        'registro_proyectos_hasta' => 'datetime',
    ];






}
```

## academicPeriod

Visibilidad: `public`. Líneas originales 39–42.

```php
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }
```

## getPeriodoAttribute

Visibilidad: `public`. Líneas originales 44–49.

```php
    public function getPeriodoAttribute(): ?string
    {
        return $this->relationLoaded('academicPeriod')
            ? $this->academicPeriod?->nombre
            : $this->academicPeriod()->value('nombre');
    }
```

## asignaturas

Visibilidad: `public`. Líneas originales 51–54.

```php
    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'cursos', 'grupo_id', 'asignatura_id');
    }
```

## registrationWindows

Visibilidad: `public`. Líneas originales 56–59.

```php
    public function registrationWindows(): HasMany
    {
        return $this->hasMany(ProjectRegistrationWindow::class, 'grupo_academico_id');
    }
```

## teacherAssignments

Visibilidad: `public`. Líneas originales 61–66.

```php
    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherGroupAssignment::class, 'grupo_academico_id')
            ->where('activo', true)
            ->whereHas('teacher');
    }
```

## projects

Visibilidad: `public`. Líneas originales 68–71.

```php
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'grupo_id');
    }
```
