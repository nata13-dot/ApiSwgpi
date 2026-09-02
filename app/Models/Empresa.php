<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';
    const CREATED_AT = 'creada_en';
    const UPDATED_AT = 'actualizada_en';

    protected $fillable = [
        'nombre',
        'rfc',
        'giro',
        'contacto_nombre',
        'contacto_cargo',
        'direccion',
        'estado_validacion', 'solicitada_por', 'validada_por', 'comentario_validacion', 'validada_en',
    ];

    protected $casts = ['validada_en' => 'datetime', 'creada_en' => 'datetime', 'actualizada_en' => 'datetime'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'empresa_id');
    }
}
