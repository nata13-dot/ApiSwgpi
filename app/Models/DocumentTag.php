<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentTag extends Model
{
    use HasFactory, BelongsToCareer;

    protected $table = 'etiquetas';
    public $timestamps = false;

    protected $fillable = ['carrera_id', 'nombre', 'color', 'descripcion', 'activo'];

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
        return $this->belongsToMany(RepositoryDocument::class, 'documento_etiquetas', 'etiqueta_id', 'documento_id');
    }

    // SCOPES
    public function scopeActivas($query) { return $query->where('activo', true); }
}
