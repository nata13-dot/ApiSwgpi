<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignaturas';
    public $timestamps = false;

    protected $fillable = ['clave', 'nombre', 'descripcion'];


    // RELACIONES
    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class, 'asignatura_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_asignatura', 'asignatura_id', 'project_id');
    }

    public function subjectGroups(): BelongsToMany
    {
        return $this->belongsToMany(SubjectGroup::class, 'subject_group_asignatura', 'asignatura_id', 'subject_group_id');
    }

    // SCOPES

}
