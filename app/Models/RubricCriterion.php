<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $fillable = [
        'semestre', 'project_id', 'clave', 'pregunta', 'orden', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'project_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
