<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competencia extends Model
{
    use HasFactory;

    protected $table = 'competencias';
    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'asignatura_id', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // RELACIONES
    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class, 'asignatura_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'competencia_id');
    }

    // SCOPES
    public function scopeActivas($query) { return $query->where('activo', true); }
}
