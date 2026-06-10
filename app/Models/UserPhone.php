<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhone extends Model
{
    protected $table = 'usuarios_telefonos';
    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'telefono',
        'tipo',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
}
