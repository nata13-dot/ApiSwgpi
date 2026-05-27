<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationAttempt extends Model
{
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
        return $this->belongsTo(Evaluation::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'id')->where('activo', true);
    }
}
