<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $table = 'entregas';

    public $timestamps = false;

    protected $fillable = [
        'entregable_id',
        'proyecto_id',
        'documento_id',
        'enviado_por',
        'entregado_en',
        'calificacion',
        'comentarios_docente',
    ];

    protected $casts = [
        'entregado_en' => 'datetime',
        'calificacion' => 'decimal:2',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'entregable_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por', 'id');
    }
}
