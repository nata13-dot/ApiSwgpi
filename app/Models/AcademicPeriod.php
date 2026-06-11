<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicPeriod extends Model
{
    protected $table = 'periodos_academicos';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'promocion_automatica',
        'promocion_aplicada_en',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'activo' => 'boolean',
        'promocion_automatica' => 'boolean',
        'promocion_aplicada_en' => 'datetime',
    ];

    public function subjectGroups(): HasMany
    {
        return $this->hasMany(SubjectGroup::class, 'periodo_id');
    }
}
