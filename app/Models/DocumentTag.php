<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentTag extends Model
{
    use HasFactory;

    protected $table = 'etiquetas_documentos';
    public $timestamps = false;

    protected $fillable = ['nombre', 'color', 'descripcion', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // RELACIONES
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'entregables_etiquetas', 'etiqueta_id', 'entregable_proyecto_id');
    }

    public function repositoryDocuments(): BelongsToMany
    {
        return $this->belongsToMany(RepositoryDocument::class, 'documentos_repositorio_etiquetas', 'etiqueta_id', 'documento_repositorio_id');
    }

    // SCOPES
    public function scopeActivas($query) { return $query->where('activo', true); }
}
