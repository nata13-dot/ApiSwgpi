<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'documento_versiones';
    public $timestamps = false;

    protected array $legacyAliases = [
        'document_id' => 'documento_id',
        'version_number' => 'numero_version',
        'file_name' => 'nombre_archivo',
        'original_name' => 'nombre_original',
        'file_path' => 'ruta_archivo',
        'disk' => 'disco',
        'mime_type' => 'mime_type',
        'size_bytes' => 'tamano_bytes',
        'checksum' => 'checksum_sha256',
        'uploaded_by' => 'subido_por',
        'created_at' => 'creado_en',
    ];

    protected $fillable = [
        'document_id', 'version_number', 'file_name', 'original_name', 'file_path',
        'disk', 'extension', 'mime_type', 'size_bytes', 'checksum', 'descripcion',
        'uploaded_by', 'created_at',
    ];

    protected $casts = [
        'numero_version' => 'integer',
        'tamano_bytes' => 'integer',
        'creado_en' => 'datetime',
    ];

    // RELACIONES
    public function document(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'documento_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por', 'id')->where('activo', true);
    }
}
