<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepositoryDocument extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'documentos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    public const CATEGORY_REPOSITORY = 'repository';
    public const CATEGORY_EVALUATION_DOCUMENT = 'evaluation_document';
    public const CATEGORY_EVALUATION_RELEASE = 'evaluation_release_sheet';
    public const CATEGORY_EVALUATION_PRESENTATION = 'evaluation_presentation';
    public const CATEGORY_THESIS_GENERAL = 'thesis_general';
    public const CATEGORY_THESIS_RESIDENCY = 'thesis_residency';
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    protected array $legacyAliases = [
        'project_id' => 'proyecto_id',
        'nombre' => 'titulo',
        'document_category' => 'categoria',
        'visibility' => 'visibilidad',
        'published_at' => 'publicado_en',
        'published_by' => 'publicado_por',
        'uploaded_by' => 'subido_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['autores', 'archivo_path', 'archivo_tipo'];

    protected ?array $pendingAuthors = null;

    protected $appends = ['autores'];

    protected $fillable = [
        'project_id',
        'nombre',
        'descripcion',
        'autores',
        'archivo_path',
        'archivo_tipo',
        'document_category',
        'visibility',
        'published_at',
        'published_by',
        'uploaded_by',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'publicado_en' => 'datetime',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'documento_etiquetas', 'documento_id', 'etiqueta_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por', 'id')->where('activo', true);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por', 'id')->where('activo', true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }

    public function authorRecords(): HasMany
    {
        return $this->hasMany(RepositoryDocumentAuthor::class, 'documento_id');
    }

    public function releaseStatuses(): HasMany
    {
        return $this->hasMany(EvaluationDocumentRelease::class, 'documento_repositorio_id');
    }

    public function getAutoresAttribute(): string
    {
        return (string) ($this->autor_nombre ?? '');
    }

    public function setAutoresAttribute(?string $value): void
    {
        $this->pendingAuthors = collect(explode(',', (string) $value))
            ->map(fn ($author) => trim($author))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        static::saving(function (RepositoryDocument $document) {
            if ($document->pendingAuthors !== null) {
                $document->autor_nombre = collect($document->pendingAuthors)->join(', ');
                $document->pendingAuthors = null;
            }
        });
    }
}
