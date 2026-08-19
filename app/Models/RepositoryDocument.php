<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Concerns\BelongsToCareer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class RepositoryDocument extends Model
{
    use HasFactory, HasLegacyAliases, BelongsToCareer;

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

    protected $appends = [
        'project_id', 'nombre', 'document_category', 'visibility', 'published_at',
        'published_by', 'uploaded_by', 'created_at', 'updated_at', 'autores',
        'archivo_path', 'archivo_tipo', 'file_available',
    ];

    protected ?array $pendingFile = null;

    protected $fillable = [
        'carrera_id',
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

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'documento_id')->latestOfMany('numero_version');
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
                self::CATEGORY_EVALUATION_DOCUMENT,
                self::CATEGORY_EVALUATION_RELEASE,
                self::CATEGORY_EVALUATION_PRESENTATION,
                'evaluacion' => 'evaluacion',
                self::CATEGORY_THESIS_GENERAL,
                self::CATEGORY_THESIS_RESIDENCY,
                'tesis' => 'tesis',
                default => $value,
            };
        }

        return $value;
    }

    public function getArchivoPathAttribute(): ?string
    {
        return $this->latestFileValue('ruta_archivo');
    }

    public function setArchivoPathAttribute(?string $value): void
    {
        $this->pendingFile = array_merge($this->pendingFile ?? [], ['path' => $value]);
    }

    public function getArchivoTipoAttribute(): ?string
    {
        return $this->latestFileValue('extension');
    }

    public function setArchivoTipoAttribute(?string $value): void
    {
        $this->pendingFile = array_merge($this->pendingFile ?? [], ['extension' => $value]);
    }

    public function getFileAvailableAttribute(): bool
    {
        $path = $this->archivo_path;

        return (bool) $path && \Illuminate\Support\Facades\Storage::disk('public')->exists($path);
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

        static::saved(function (RepositoryDocument $document): void {
            $path = $document->pendingFile['path'] ?? null;
            if (!$path) {
                $document->pendingFile = null;
                return;
            }

            $extension = strtolower((string) ($document->pendingFile['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)));
            $version = ((int) DB::table('documento_versiones')
                ->where('documento_id', $document->id)
                ->max('numero_version')) + 1;
            DB::table('documento_versiones')->insert([
                'documento_id' => $document->id,
                'numero_version' => $version,
                'nombre_archivo' => basename($path),
                'ruta_archivo' => $path,
                'extension' => $extension ?: null,
                'mime_type' => null,
                'tamano_bytes' => null,
                'descripcion' => $version === 1 ? 'Versión inicial' : 'Archivo actualizado',
                'subido_por' => auth('api')->id() ?: $document->subido_por,
                'creado_en' => now(),
            ]);
            $document->pendingFile = null;
            $document->unsetRelation('latestVersion');
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
