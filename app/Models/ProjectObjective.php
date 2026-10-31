<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Objetivo específico de un proyecto, en el orden en que lo capturó el equipo. */
class ProjectObjective extends Model
{
    protected $table = 'proyecto_objetivos';

    public $timestamps = false;

    protected $fillable = ['proyecto_id', 'orden', 'descripcion'];

    protected $casts = ['orden' => 'integer'];
}
