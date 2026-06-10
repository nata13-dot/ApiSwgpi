<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class EvaluationScore extends Model
{
    use HasLegacyAliases;

    protected $table = 'respuestas_evaluacion';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'evaluation_id' => 'intento_evaluacion_id',
        'rubric_criterion_id' => 'criterio_rubrica_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['teacher_id', 'criterio', 'nivel'];

    protected $appends = [
        'evaluation_id',
        'teacher_id',
        'criterio',
        'nivel',
    ];

    protected $fillable = [
        'evaluation_id', 'teacher_id', 'criterio', 'nivel', 'puntaje', 'comentario',
    ];

    protected $casts = [
        'puntaje' => 'integer',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'intento_evaluacion_id');
    }

    public function teacher(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            EvaluationAttempt::class,
            'id',
            'id',
            'intento_evaluacion_id',
            'docente_id'
        )->where('usuarios.activo', true);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(EvaluationAttempt::class, 'intento_evaluacion_id');
    }

    public function getEvaluationIdAttribute(): ?int
    {
        return $this->attempt?->evaluacion_id;
    }

    public function getTeacherIdAttribute(): ?string
    {
        if ($this->relationLoaded('teacher')) {
            return $this->teacher?->id;
        }

        return $this->attempt?->docente_id;
    }

    public function getCriterioAttribute(): ?string
    {
        if ($this->relationLoaded('criterion')) {
            return $this->criterion?->clave;
        }

        return $this->criterion?->clave;
    }

    public function getNivelAttribute(): ?string
    {
        return $this->valor;
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'criterio_rubrica_id');
    }
}
