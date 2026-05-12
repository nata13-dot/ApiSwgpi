<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'project_id', 'evaluation_room_id', 'semestre', 'etapa', 'sala', 'fecha_exposicion',
        'estado', 'resultado', 'created_by',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'fecha_exposicion' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(EvaluationRoom::class, 'evaluation_room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class, 'evaluation_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(EvaluationAttempt::class, 'evaluation_id');
    }

    public function getAverageAttribute(): float
    {
        $average = $this->scores->avg('puntaje');
        return $average === null ? 0 : round(($average / 3) * 100, 2);
    }
}
