<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;

class CareerModule extends Model
{
    use BelongsToCareer;

    protected $table = 'carrera_modulos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = ['carrera_id', 'modulo', 'habilitado', 'configuracion'];

    protected $casts = [
        'habilitado' => 'boolean',
        'configuracion' => 'array',
    ];
}

