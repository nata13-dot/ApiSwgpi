<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryDocumentAuthor extends Model
{
    protected $table = 'documentos_repositorio_autores';

    public $timestamps = false;

    protected $fillable = [
        'documento_repositorio_id',
        'usuario_id',
        'nombre_autor',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_repositorio_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
}
