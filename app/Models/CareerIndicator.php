<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;

class CareerIndicator extends Model
{
    use BelongsToCareer;

    protected $table = 'indicadores_carrera';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'carrera_id',
        'modulo',
        'clave',
        'nombre',
        'descripcion',
        'unidad',
        'valor_actual',
        'valor_meta',
        'color',
        'icono',
        'activo',
    ];

    protected $casts = [
        'valor_actual' => 'decimal:2',
        'valor_meta' => 'decimal:2',
        'activo' => 'boolean',
    ];
}

