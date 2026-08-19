<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCareer extends Model
{
    protected $table = 'usuario_carrera';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'usuario_id',
        'carrera_id',
        'perfil_id',
        'es_principal',
        'activo',
        'asignado_por',
    ];

    protected $casts = [
        'carrera_id' => 'integer',
        'perfil_id' => 'integer',
        'es_principal' => 'boolean',
        'activo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'carrera_id');
    }
}

