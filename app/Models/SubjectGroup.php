<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectGroup extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'semestre', 'grupo', 'periodo', 'activo'];

    protected $casts = [
        'semestre' => 'integer',
        'activo' => 'boolean',
    ];

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'subject_group_asignatura', 'subject_group_id', 'asignatura_id')
            ->withTimestamps();
    }

    public function registrationWindows(): HasMany
    {
        return $this->hasMany(ProjectRegistrationWindow::class);
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherGroupAssignment::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'subject_group_id');
    }
}
