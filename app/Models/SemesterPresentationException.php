<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SemesterPresentationException extends Model
{
    protected $table = 'excepciones_presentacion_semestre';

    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected $fillable = [
        'periodo_id',
        'proyecto_id',
        'usuario_id',
        'semestre_presentacion',
        'motivo',
        'activo',
    ];

    protected $casts = [
        'periodo_id' => 'integer',
        'proyecto_id' => 'integer',
        'semestre_presentacion' => 'integer',
        'activo' => 'boolean',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
}
