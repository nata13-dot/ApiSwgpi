<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationDocumentRelease extends Model
{
    protected $table = 'liberaciones_documentos_evaluacion';

    protected $fillable = [
        'documento_repositorio_id',
        'alumno_id',
        'liberado',
        'revisado_por',
        'revisado_en',
    ];

    protected $casts = [
        'liberado' => 'boolean',
        'revisado_en' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_repositorio_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alumno_id', 'id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por', 'id');
    }
}
