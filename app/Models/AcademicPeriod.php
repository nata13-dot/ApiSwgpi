<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    protected $table = 'periodos_academicos';

    public $timestamps = false;

    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'activo'];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
    ];
}
