# TeacherGroupAssignment

Fuente: [app/Models/TeacherGroupAssignment.php](../../../../../app/Models/TeacherGroupAssignment.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherGroupAssignment extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'asignaciones_docentes_grupos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'teacher_id' => 'docente_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = ['subject_group_id', 'asignatura_id', 'teacher_id', 'labor', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'asignatura_id' => 'integer',
    ];



}
```

## subjectGroup

Visibilidad: `public`. Líneas originales 32–35.

```php
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }
```

## asignatura

Visibilidad: `public`. Líneas originales 37–40.

```php
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }
```

## teacher

Visibilidad: `public`. Líneas originales 42–45.

```php
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id')->where('activo', true);
    }
```
