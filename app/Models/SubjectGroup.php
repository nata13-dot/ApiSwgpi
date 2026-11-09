<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Concerns\Archivable;
use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectGroup extends Model
{
    use Archivable, HasFactory, HasLegacyAliases, BelongsToCareer;

    protected $table = 'grupos_academicos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'id',
        'grupo' => 'clave_grupo',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = ['carrera_id', 'nombre', 'semestre', 'grupo', 'periodo_id', 'registro_proyectos_desde', 'registro_proyectos_hasta', 'activo'];

    protected $appends = ['periodo'];

    protected $casts = [
        'semestre' => 'integer',
        'activo' => 'boolean',
        'registro_proyectos_desde' => 'datetime',
        'registro_proyectos_hasta' => 'datetime',
    ];

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }

    // Catálogo pequeño: se lee una vez por petición en lugar de una consulta por grupo serializado.
    public function getPeriodoAttribute(): ?string
    {
        if ($this->relationLoaded('academicPeriod')) {
            return $this->academicPeriod?->nombre;
        }
        $names = \App\Support\RequestMemo::remember('nombres-periodo', fn () => AcademicPeriod::query()->pluck('nombre', 'id')->all());

        return $names[$this->periodo_id] ?? null;
    }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'cursos', 'grupo_id', 'asignatura_id');
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
        return $this->hasMany(Project::class, 'grupo_id');
    }
}
