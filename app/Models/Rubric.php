<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    use BelongsToCareer;

    protected $table = 'rubricas';
    public $timestamps = false;

    protected $fillable = ['carrera_id', 'nombre', 'etapa', 'semestre', 'activa'];

    protected $casts = [
        'semestre' => 'integer',
        'activa' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(EvaluationRoom::class, 'rubrica_id');
    }
}
