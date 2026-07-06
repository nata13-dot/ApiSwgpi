<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    protected $table = 'rubricas';
    public $timestamps = false;

    protected $casts = [
        'semestre' => 'integer',
        'activa' => 'boolean',
    ];

    public function rooms(): HasMany
    {
        return $this->hasMany(EvaluationRoom::class, 'rubrica_id');
    }
}
