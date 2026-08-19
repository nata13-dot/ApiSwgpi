<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asignatura extends Model
{
    use HasFactory, BelongsToCareer;

    protected $table = 'asignaturas';
    public $timestamps = false;

    protected $fillable = ['carrera_id', 'clave', 'nombre', 'descripcion', 'activo'];


    // RELACIONES
    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class, 'asignatura_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'cursos', 'asignatura_id', 'grupo_id', 'id', 'grupo_id')
            ->wherePivot('activo', true);
    }

    public function subjectGroups(): BelongsToMany
    {
        return $this->belongsToMany(SubjectGroup::class, 'cursos', 'asignatura_id', 'grupo_id');
    }

    // SCOPES

}
