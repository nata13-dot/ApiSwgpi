<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityNotification extends Model
{
    protected $table = 'notificaciones_actividad';
    const CREATED_AT = 'creada_en';
    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'actor_id',
        'tipo',
        'titulo',
        'mensaje',
        'url',
        'leida_en',
    ];

    protected $casts = [
        'leida_en' => 'datetime',
        'creada_en' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
