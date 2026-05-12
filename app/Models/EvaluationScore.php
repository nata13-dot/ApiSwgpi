<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationScore extends Model
{
    protected $fillable = [
        'evaluation_id', 'teacher_id', 'criterio', 'nivel', 'puntaje', 'comentario',
    ];

    protected $casts = [
        'puntaje' => 'integer',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'id');
    }
}