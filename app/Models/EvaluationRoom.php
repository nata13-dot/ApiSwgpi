<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRoom extends Model
{
    protected $fillable = [
        'nombre', 'salon', 'semestre', 'fecha_evaluacion',
        'teacher_evaluation_minutes', 'project_presentation_minutes',
        'max_attempts', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'fecha_evaluacion' => 'datetime',
        'teacher_evaluation_minutes' => 'integer',
        'project_presentation_minutes' => 'integer',
        'max_attempts' => 'integer',
        'activo' => 'boolean',
    ];

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evaluation_room_teacher', 'evaluation_room_id', 'teacher_id')
            ->where('users.activo', true)
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'evaluation_room_project')->withTimestamps();
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
