<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRoom extends Model
{
    use HasLegacyAliases;

    protected $table = 'salas_evaluacion';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'responsible_teacher_id' => 'creado_por',
        'fecha_evaluacion' => 'inicia_en',
        'fecha_fin_evaluacion' => 'termina_en',
        'teacher_evaluation_minutes' => 'minutos_evaluacion_docente',
        'project_presentation_minutes' => 'minutos_presentacion_proyecto',
        'max_attempts' => 'max_intentos',
        'created_by' => 'creado_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['semestre', 'etapa'];

    protected $fillable = [
        'nombre', 'salon', 'semestre', 'etapa', 'responsible_teacher_id', 'fecha_evaluacion', 'fecha_fin_evaluacion',
        'teacher_evaluation_minutes', 'project_presentation_minutes',
        'max_attempts', 'sequence_locked', 'current_order', 'completed_at', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'fecha_evaluacion' => 'datetime',
        'fecha_fin_evaluacion' => 'datetime',
        'teacher_evaluation_minutes' => 'integer',
        'project_presentation_minutes' => 'integer',
        'max_attempts' => 'integer',
        'sequence_locked' => 'boolean',
        'current_order' => 'integer',
        'completed_at' => 'datetime',
        'activo' => 'boolean',
    ];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sala_docentes', 'sala_id', 'docente_id')
            ->where('usuarios.activo', true)
            ->withPivot('rol');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubrica_id');
    }

    public function getSemestreAttribute(): ?int
    {
        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $semester = $this->rubric?->semestre;

        return $semester === null ? null : (int) $semester;
    }

    public function getEtapaAttribute(): ?string
    {
        if (array_key_exists('etapa', $this->attributes)) {
            return $this->attributes['etapa'];
        }

        return $this->rubric?->etapa;
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'evaluaciones', 'sala_id', 'proyecto_id')
            ->withPivot(['orden_presentacion'])
            ->orderBy('evaluaciones.orden_presentacion')
            ->orderBy('proyectos.titulo');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'sala_id');
    }

    public function responsibleTeacher()
    {
        return $this->belongsTo(User::class, 'creado_por', 'id')->where('activo', true);
    }
}
