<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class RepositoryDocument extends Model
{
    use BelongsToCareer, HasFactory, HasLegacyAliases;

    protected $table = 'documentos';

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    public const CATEGORY_REPOSITORY = 'repository';

    public const CATEGORY_DELIVERABLE = 'deliverable';

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

    protected $appends = [
        'project_id', 'nombre', 'document_category', 'visibility', 'published_at',
        'published_by', 'uploaded_by', 'created_at', 'updated_at', 'autores',
        'archivo_path', 'archivo_tipo', 'file_available',
    ];

    protected $fillable = [
        'carrera_id',
        'project_id',
        'nombre',
        'descripcion',
        'estado',
        'autores',
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

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'documento_id')->latestOfMany('numero_version');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'documento_id')->orderBy('numero_version');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'documento_id');
    }

    public function getProjectIdAttribute(): ?int
    {
        $value = $this->attributes['proyecto_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function getNombreAttribute(): string
    {
        return (string) ($this->attributes['titulo'] ?? '');
    }

    public function getDocumentCategoryAttribute(): string
    {
        return $this->getCategoriaAttribute($this->attributes['categoria'] ?? '');
    }

    public function getVisibilityAttribute(): string
    {
        return $this->getVisibilidadAttribute($this->attributes['visibilidad'] ?? '');
    }

    public function getPublishedAtAttribute()
    {
        return $this->publicado_en;
    }

    public function getPublishedByAttribute(): ?string
    {
        return $this->attributes['publicado_por'] ?? null;
    }

    public function getUploadedByAttribute(): ?string
    {
        return $this->attributes['subido_por'] ?? null;
    }

    public function getCreatedAtAttribute()
    {
        return $this->creado_en;
    }

    public function getUpdatedAtAttribute()
    {
        return $this->actualizado_en;
    }

    public function getVisibilidadAttribute($value): string
    {
        return match ($value) {
            'publico' => self::VISIBILITY_PUBLIC,
            'privado' => self::VISIBILITY_PRIVATE,
            default => (string) $value,
        };
    }

    public function setVisibilidadAttribute($value): void
    {
        $this->attributes['visibilidad'] = $this->mapLegacyValue('visibility', $value);
    }

    public function getCategoriaAttribute($value): string
    {
        return match ($value) {
            'repositorio' => self::CATEGORY_REPOSITORY,
            'entregable' => self::CATEGORY_DELIVERABLE,
            'evaluacion' => self::CATEGORY_EVALUATION_DOCUMENT,
            'tesis' => self::CATEGORY_THESIS_GENERAL,
            default => (string) $value,
        };
    }

    public function setCategoriaAttribute($value): void
    {
        $this->attributes['categoria'] = $this->mapLegacyValue('document_category', $value);
    }

    public function mapLegacyValue(string $column, mixed $value): mixed
    {
        $column = str_contains($column, '.') ? explode('.', $column, 2)[1] : $column;

        if (in_array($column, ['visibility', 'visibilidad'], true)) {
            return match ($value) {
                self::VISIBILITY_PUBLIC, 'publico' => 'publico',
                self::VISIBILITY_PRIVATE, 'privado' => 'privado',
                default => $value,
            };
        }

        if (in_array($column, ['document_category', 'categoria'], true)) {
            return match ($value) {
                self::CATEGORY_REPOSITORY, 'repositorio' => 'repositorio',
                self::CATEGORY_DELIVERABLE, 'entregable' => 'entregable',
                self::CATEGORY_EVALUATION_DOCUMENT, 'evaluacion' => 'evaluacion',
                self::CATEGORY_EVALUATION_RELEASE => self::CATEGORY_EVALUATION_RELEASE,
                self::CATEGORY_EVALUATION_PRESENTATION => self::CATEGORY_EVALUATION_PRESENTATION,
                self::CATEGORY_THESIS_GENERAL, 'tesis' => 'tesis',
                self::CATEGORY_THESIS_RESIDENCY => self::CATEGORY_THESIS_RESIDENCY,
                default => $value,
            };
        }

        return $value;
    }

    public function getArchivoPathAttribute(): ?string
    {
        return $this->latestFileValue('ruta_archivo');
    }

    public function getArchivoTipoAttribute(): ?string
    {
        return $this->latestFileValue('extension');
    }

    public function getFileAvailableAttribute(): bool
    {
        $version = $this->relationLoaded('latestVersion')
            ? $this->getRelation('latestVersion')
            : $this->latestVersion()->first();
        if (! $version?->ruta_archivo) {
            return false;
        }

        $disk = $version->disco ?: config('uploads.legacy_disk', 'legacy_public');
        if (isset(config('filesystems.disks')[$disk])
            && Storage::disk($disk)->exists($version->ruta_archivo)) {
            return true;
        }

        $legacy = config('uploads.legacy_disk', 'legacy_public');

        return config('uploads.legacy_public_fallback', true)
            && $disk !== $legacy
            && Storage::disk($legacy)->exists($version->ruta_archivo);
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

    private function latestFileValue(string $column): ?string
    {
        $version = $this->relationLoaded('latestVersion')
            ? $this->getRelation('latestVersion')
            : $this->latestVersion()->first();

        return $version?->getRawOriginal($column);
    }
}
