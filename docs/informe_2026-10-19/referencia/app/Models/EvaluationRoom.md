# EvaluationRoom

Fuente: [app/Models/EvaluationRoom.php](../../../../../app/Models/EvaluationRoom.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRoom extends Model
{
    use HasLegacyAliases, BelongsToCareer;

    protected $table = 'salas_evaluacion';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'responsible_teacher_id' => 'responsable_id',
        'fecha_evaluacion' => 'inicia_en',
        'fecha_fin_evaluacion' => 'termina_en',
        'teacher_evaluation_minutes' => 'minutos_evaluacion_docente',
        'project_presentation_minutes' => 'minutos_presentacion_proyecto',
        'max_attempts' => 'max_intentos',
        'sequence_locked' => 'secuencia_bloqueada',
        'current_order' => 'orden_actual',
        'sequence_version' => 'secuencia_version',
        'completed_at' => 'completada_en',
        'timer_status' => 'temporizador_estado',
        'timer_order' => 'temporizador_orden',
        'timer_duration_seconds' => 'temporizador_duracion_segundos',
        'timer_started_at' => 'temporizador_iniciado_en',
        'timer_ends_at' => 'temporizador_finaliza_en',
        'timer_remaining_seconds' => 'temporizador_restante_segundos',
        'timer_updated_by' => 'temporizador_actualizado_por',
        'allow_late_evaluations' => 'permite_evaluacion_fuera_horario',
        'late_evaluation_until' => 'evaluacion_fuera_horario_hasta',
        'late_evaluation_reason' => 'motivo_evaluacion_fuera_horario',
        'schedule_updated_by' => 'horario_actualizado_por',
        'schedule_updated_at' => 'horario_actualizado_en',
        'created_by' => 'creado_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['semestre', 'etapa'];

    protected $fillable = [
        'periodo_id', 'salon_id', 'carrera_id', 'rubrica_id', 'nombre', 'salon', 'semestre', 'etapa', 'responsible_teacher_id', 'fecha_evaluacion', 'fecha_fin_evaluacion',
        'teacher_evaluation_minutes', 'project_presentation_minutes',
        'max_attempts', 'estado', 'sequence_locked', 'current_order', 'sequence_version', 'completed_at',
        'timer_status', 'timer_order', 'timer_duration_seconds', 'timer_started_at', 'timer_ends_at',
        'timer_remaining_seconds', 'timer_updated_by', 'created_by', 'activo',
        'allow_late_evaluations', 'late_evaluation_until', 'late_evaluation_reason',
        'schedule_updated_by', 'schedule_updated_at',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'fecha_evaluacion' => 'datetime',
        'inicia_en' => 'datetime',
        'fecha_fin_evaluacion' => 'datetime',
        'termina_en' => 'datetime',
        'teacher_evaluation_minutes' => 'integer',
        'minutos_evaluacion_docente' => 'integer',
        'project_presentation_minutes' => 'integer',
        'minutos_presentacion_proyecto' => 'integer',
        'max_attempts' => 'integer',
        'max_intentos' => 'integer',
        'sequence_locked' => 'boolean',
        'secuencia_bloqueada' => 'boolean',
        'current_order' => 'integer',
        'orden_actual' => 'integer',
        'sequence_version' => 'integer',
        'secuencia_version' => 'integer',
        'completed_at' => 'datetime',
        'completada_en' => 'datetime',
        'timer_order' => 'integer',
        'temporizador_orden' => 'integer',
        'timer_duration_seconds' => 'integer',
        'temporizador_duracion_segundos' => 'integer',
        'timer_started_at' => 'datetime',
        'temporizador_iniciado_en' => 'datetime',
        'timer_ends_at' => 'datetime',
        'temporizador_finaliza_en' => 'datetime',
        'timer_remaining_seconds' => 'integer',
        'temporizador_restante_segundos' => 'integer',
        'allow_late_evaluations' => 'boolean',
        'permite_evaluacion_fuera_horario' => 'boolean',
        'late_evaluation_until' => 'datetime',
        'evaluacion_fuera_horario_hasta' => 'datetime',
        'schedule_updated_at' => 'datetime',
        'horario_actualizado_en' => 'datetime',
        'activo' => 'boolean',
    ];







}
```

## teachers

Visibilidad: `public`. Líneas originales 99–104.

```php
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sala_docentes', 'sala_id', 'docente_id')
            ->withoutGlobalScope('careerMembership')
            ->withPivot('rol');
    }
```

## rubric

Visibilidad: `public`. Líneas originales 106–109.

```php
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubrica_id');
    }
```

## getSemestreAttribute

Visibilidad: `public`. Líneas originales 111–120.

```php
    public function getSemestreAttribute(): ?int
    {
        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $semester = $this->rubric?->semestre;

        return $semester === null ? null : (int) $semester;
    }
```

## getEtapaAttribute

Visibilidad: `public`. Líneas originales 122–129.

```php
    public function getEtapaAttribute(): ?string
    {
        if (array_key_exists('etapa', $this->attributes)) {
            return $this->attributes['etapa'];
        }

        return $this->rubric?->etapa;
    }
```

## projects

Visibilidad: `public`. Líneas originales 131–137.

```php
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'evaluaciones', 'sala_id', 'proyecto_id')
            ->withPivot(['id', 'orden_presentacion', 'estado', 'finalizada_en'])
            ->orderBy('evaluaciones.orden_presentacion')
            ->orderBy('proyectos.titulo');
    }
```

## evaluations

Visibilidad: `public`. Líneas originales 139–142.

```php
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'sala_id');
    }
```

## responsibleTeacher

Visibilidad: `public`. Líneas originales 144–147.

```php
    public function responsibleTeacher()
    {
        return $this->belongsTo(User::class, 'responsable_id', 'id')->withoutGlobalScope('careerMembership');
    }
```
