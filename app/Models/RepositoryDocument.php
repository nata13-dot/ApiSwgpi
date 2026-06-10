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

    protected $table = 'documentos_repositorio';
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
        'archivo_path' => 'archivo_ruta',
        'document_category' => 'categoria',
        'visibility' => 'visibilidad',
        'published_at' => 'publicado_en',
        'published_by' => 'publicado_por',
        'uploaded_by' => 'subido_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected array $legacyVirtualColumns = ['autores'];

    protected ?array $pendingAuthors = null;

    protected $appends = ['autores'];

    protected $with = ['authorRecords'];

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
        return $this->belongsToMany(DocumentTag::class, 'documentos_repositorio_etiquetas', 'documento_repositorio_id', 'etiqueta_id');
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
        return $this->hasMany(RepositoryDocumentAuthor::class, 'documento_repositorio_id');
    }

    public function releaseStatuses(): HasMany
    {
        return $this->hasMany(EvaluationDocumentRelease::class, 'documento_repositorio_id');
    }

    public function getAutoresAttribute(): string
    {
        return $this->authorRecords
            ->pluck('nombre_autor')
            ->filter()
            ->join(', ');
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
        static::saved(function (RepositoryDocument $document) {
            if ($document->pendingAuthors === null) {
                return;
            }

            $document->authorRecords()->delete();
            $document->authorRecords()->createMany(
                collect($document->pendingAuthors)
                    ->map(fn ($author) => ['nombre_autor' => $author])
                    ->all()
            );
            $document->pendingAuthors = null;
            $document->load('authorRecords');
        });
    }
}
