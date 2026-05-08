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

    protected $fillable = ['nombre', 'asignatura_id', 'fecha_inicio', 'fecha_fin'];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
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

}
