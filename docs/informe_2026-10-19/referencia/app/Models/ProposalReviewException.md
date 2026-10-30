# ProposalReviewException

Fuente: [app/Models/ProposalReviewException.php](../../../../../app/Models/ProposalReviewException.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalReviewException extends Model
{
    use HasLegacyAliases;

    protected $table = 'excepciones_revision_propuesta';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'project_id' => 'proyecto_id',
        'teacher_id' => 'docente_id',
        'student_id' => 'estudiante_id',
        'notes' => 'motivo',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = [
        'asignatura_id', 'subject_group_id', 'teacher_id', 'student_id', 'notes', 'activo',
    ];

    protected $appends = ['subject_group_id', 'teacher_id', 'student_id', 'notes'];

    protected $hidden = [
        'grupo_academico_id',
        'docente_id',
        'estudiante_id',
        'motivo',
    ];

    protected $casts = [
        'asignatura_id' => 'integer',
        'grupo_academico_id' => 'integer',
        'activo' => 'boolean',
    ];








}
```

## asignatura

Visibilidad: `public`. Líneas originales 46–49.

```php
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }
```

## subjectGroup

Visibilidad: `public`. Líneas originales 51–54.

```php
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }
```

## teacher

Visibilidad: `public`. Líneas originales 56–59.

```php
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id')->where('activo', true);
    }
```

## student

Visibilidad: `public`. Líneas originales 61–64.

```php
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id', 'id')->where('activo', true);
    }
```

## getSubjectGroupIdAttribute

Visibilidad: `public`. Líneas originales 66–70.

```php
    public function getSubjectGroupIdAttribute(): ?int
    {
        $value = $this->attributes['grupo_academico_id'] ?? null;
        return $value === null ? null : (int) $value;
    }
```

## getTeacherIdAttribute

Visibilidad: `public`. Líneas originales 72–75.

```php
    public function getTeacherIdAttribute(): ?string
    {
        return $this->attributes['docente_id'] ?? null;
    }
```

## getStudentIdAttribute

Visibilidad: `public`. Líneas originales 77–80.

```php
    public function getStudentIdAttribute(): ?string
    {
        return $this->attributes['estudiante_id'] ?? null;
    }
```

## getNotesAttribute

Visibilidad: `public`. Líneas originales 82–85.

```php
    public function getNotesAttribute(): ?string
    {
        return $this->attributes['motivo'] ?? null;
    }
```
