<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAttempt extends Model
{
    use HasLegacyAliases;

    protected $table = 'dictamenes_docentes';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'evaluation_id' => 'evaluacion_id',
        'teacher_id' => 'docente_id',
        'attempts_count' => 'intentos_realizados',
        'general_comment' => 'comentario_general',
        'last_submitted_at' => 'enviado_en',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = [
        'evaluation_id', 'teacher_id', 'attempts_count', 'general_comment',
        'apto_titulacion', 'last_submitted_at',
    ];

    protected $casts = [
        'attempts_count' => 'integer',
        'apto_titulacion' => 'boolean',
        'last_submitted_at' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluacion_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id')->where('activo', true);
    }
}
