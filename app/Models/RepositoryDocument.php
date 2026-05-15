<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RepositoryDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'autores',
        'archivo_path',
        'archivo_tipo',
        'uploaded_by',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'repository_document_tag', 'repository_document_id', 'document_tag_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id')->where('activo', true);
    }
}
