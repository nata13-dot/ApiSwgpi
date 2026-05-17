<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRoom extends Model
{
    protected $fillable = [
        'nombre', 'salon', 'semestre', 'responsible_teacher_id', 'fecha_evaluacion',
        'teacher_evaluation_minutes', 'project_presentation_minutes',
        'max_attempts', 'sequence_locked', 'current_order', 'completed_at', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'fecha_evaluacion' => 'datetime',
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
        return $this->belongsToMany(User::class, 'evaluation_room_teacher', 'evaluation_room_id', 'teacher_id')
            ->where('users.activo', true)
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'evaluation_room_project')
            ->withPivot(['presentation_order', 'status'])
            ->withTimestamps()
            ->orderBy('evaluation_room_project.presentation_order')
            ->orderBy('projects.title');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function responsibleTeacher()
    {
        return $this->belongsTo(User::class, 'responsible_teacher_id', 'id')->where('activo', true);
    }
}
