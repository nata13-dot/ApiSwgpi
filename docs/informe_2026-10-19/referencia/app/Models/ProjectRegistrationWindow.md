# ProjectRegistrationWindow

Fuente: [app/Models/ProjectRegistrationWindow.php](../../../../../app/Models/ProjectRegistrationWindow.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRegistrationWindow extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'ventanas_registro_proyectos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'starts_at' => 'inicia_en',
        'ends_at' => 'termina_en',
        'notes' => 'notas',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $appends = ['subject_group_id', 'starts_at', 'ends_at', 'notes'];

    protected $hidden = [
        'grupo_academico_id',
        'inicia_en',
        'termina_en',
        'notas',
        'creado_en',
        'actualizado_en',
    ];

    protected $fillable = ['subject_group_id', 'starts_at', 'ends_at', 'activo', 'notes'];

    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'activo' => 'boolean',
    ];





}
```

## subjectGroup

Visibilidad: `public`. Líneas originales 46–49.

```php
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }
```

## getSubjectGroupIdAttribute

Visibilidad: `public`. Líneas originales 51–55.

```php
    public function getSubjectGroupIdAttribute(): ?int
    {
        $value = $this->attributes['grupo_academico_id'] ?? null;
        return $value === null ? null : (int) $value;
    }
```

## getStartsAtAttribute

Visibilidad: `public`. Líneas originales 57–61.

```php
    public function getStartsAtAttribute(): mixed
    {
        $value = $this->attributes['inicia_en'] ?? null;
        return $value === null ? null : $this->asDateTime($value);
    }
```

## getEndsAtAttribute

Visibilidad: `public`. Líneas originales 63–67.

```php
    public function getEndsAtAttribute(): mixed
    {
        $value = $this->attributes['termina_en'] ?? null;
        return $value === null ? null : $this->asDateTime($value);
    }
```

## getNotesAttribute

Visibilidad: `public`. Líneas originales 69–72.

```php
    public function getNotesAttribute(): ?string
    {
        return $this->attributes['notas'] ?? null;
    }
```
