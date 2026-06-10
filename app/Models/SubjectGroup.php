<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectGroup extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'grupos_academicos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = ['nombre', 'semestre', 'grupo', 'periodo_id', 'activo'];

    protected $appends = ['periodo'];

    protected $casts = [
        'semestre' => 'integer',
        'activo' => 'boolean',
    ];

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }

    public function getPeriodoAttribute(): ?string
    {
        return $this->relationLoaded('academicPeriod')
            ? $this->academicPeriod?->nombre
            : $this->academicPeriod()->value('nombre');
    }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'grupos_asignaturas', 'grupo_academico_id', 'asignatura_id')
            ->withTimestamps();
    }

    public function registrationWindows(): HasMany
    {
        return $this->hasMany(ProjectRegistrationWindow::class, 'grupo_academico_id');
    }

    public function teacherAssignments(): HasMany
    {
        return $this->hasMany(TeacherGroupAssignment::class, 'grupo_academico_id')
            ->where('activo', true)
            ->whereHas('teacher');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'grupo_academico_id');
    }
}
