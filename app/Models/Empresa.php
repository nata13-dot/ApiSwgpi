<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    protected $table = 'empresas';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'giro',
        'contacto_nombre',
        'contacto_cargo',
        'direccion',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'empresa_id');
    }
}
