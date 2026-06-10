<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'versiones_documentos';
    public $timestamps = false;

    protected array $legacyAliases = [
        'deliverable_id' => 'entregable_proyecto_id',
        'version_number' => 'numero_version',
        'archivo_path' => 'archivo_ruta',
        'uploaded_by' => 'subido_por',
        'created_at' => 'creado_en',
    ];

    protected $fillable = ['deliverable_id', 'version_number', 'descripcion', 'archivo_path', 'uploaded_by'];

    // RELACIONES
    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'entregable_proyecto_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por', 'id')->where('activo', true);
    }
}
