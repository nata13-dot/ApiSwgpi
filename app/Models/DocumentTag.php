<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentTag extends Model
{
    use HasFactory;

    protected $table = 'document_tags';
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'descripcion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // RELACIONES
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'deliverable_document_tag', 'document_tag_id', 'deliverable_id');
    }

    public function repositoryDocuments(): BelongsToMany
    {
        return $this->belongsToMany(RepositoryDocument::class, 'repository_document_tag', 'document_tag_id', 'repository_document_id');
    }

    // SCOPES
    public function scopeActivas($query) { return $query->where('activo', true); }
}
