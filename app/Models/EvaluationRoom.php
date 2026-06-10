<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Pivots\EvaluationRoomProjectPivot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRoom extends Model
{
    use HasLegacyAliases;

    protected $table = 'salas_evaluacion';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'responsible_teacher_id' => 'creado_por',
        'created_by' => 'creado_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

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
        return $this->belongsToMany(User::class, 'salas_evaluacion_docentes', 'sala_evaluacion_id', 'docente_id')
            ->where('usuarios.activo', true)
            ->withPivot('rol');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'salas_evaluacion_proyectos', 'sala_evaluacion_id', 'proyecto_id')
            ->using(EvaluationRoomProjectPivot::class)
            ->withPivot(['orden'])
            ->orderBy('salas_evaluacion_proyectos.orden')
            ->orderBy('proyectos.titulo');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'sala_evaluacion_id');
    }

    public function responsibleTeacher()
    {
        return $this->belongsTo(User::class, 'creado_por', 'id')->where('activo', true);
    }
}
