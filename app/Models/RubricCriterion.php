<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $fillable = [
        'semestre', 'clave', 'pregunta', 'orden', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];
}