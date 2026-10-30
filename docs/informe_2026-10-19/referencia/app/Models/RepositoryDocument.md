# RepositoryDocument

Fuente: [app/Models/RepositoryDocument.php](../../../../../app/Models/RepositoryDocument.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
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






























}
```

## tags

Visibilidad: `public`. Líneas originales 85–88.

```php
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'documento_etiquetas', 'documento_id', 'etiqueta_id');
    }
```

## uploader

Visibilidad: `public`. Líneas originales 90–93.

```php
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por', 'id')->withoutGlobalScope('careerMembership');
    }
```

## publisher

Visibilidad: `public`. Líneas originales 95–98.

```php
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publicado_por', 'id')->where('activo', true);
    }
```

## project

Visibilidad: `public`. Líneas originales 100–103.

```php
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }
```

## authorRecords

Visibilidad: `public`. Líneas originales 105–108.

```php
    public function authorRecords(): HasMany
    {
        return $this->hasMany(RepositoryDocumentAuthor::class, 'documento_id');
    }
```

## releaseStatuses

Visibilidad: `public`. Líneas originales 110–113.

```php
    public function releaseStatuses(): HasMany
    {
        return $this->hasMany(EvaluationDocumentRelease::class, 'documento_repositorio_id');
    }
```

## latestVersion

Visibilidad: `public`. Líneas originales 115–118.

```php
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'documento_id')->latestOfMany('numero_version');
    }
```

## versions

Visibilidad: `public`. Líneas originales 120–123.

```php
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'documento_id')->orderBy('numero_version');
    }
```

## delivery

Visibilidad: `public`. Líneas originales 125–128.

```php
    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class, 'documento_id');
    }
```

## getProjectIdAttribute

Visibilidad: `public`. Líneas originales 130–135.

```php
    public function getProjectIdAttribute(): ?int
    {
        $value = $this->attributes['proyecto_id'] ?? null;

        return $value === null ? null : (int) $value;
    }
```

## getNombreAttribute

Visibilidad: `public`. Líneas originales 137–140.

```php
    public function getNombreAttribute(): string
    {
        return (string) ($this->attributes['titulo'] ?? '');
    }
```

## getDocumentCategoryAttribute

Visibilidad: `public`. Líneas originales 142–145.

```php
    public function getDocumentCategoryAttribute(): string
    {
        return $this->getCategoriaAttribute($this->attributes['categoria'] ?? '');
    }
```

## getVisibilityAttribute

Visibilidad: `public`. Líneas originales 147–150.

```php
    public function getVisibilityAttribute(): string
    {
        return $this->getVisibilidadAttribute($this->attributes['visibilidad'] ?? '');
    }
```

## getPublishedAtAttribute

Visibilidad: `public`. Líneas originales 152–155.

```php
    public function getPublishedAtAttribute()
    {
        return $this->publicado_en;
    }
```

## getPublishedByAttribute

Visibilidad: `public`. Líneas originales 157–160.

```php
    public function getPublishedByAttribute(): ?string
    {
        return $this->attributes['publicado_por'] ?? null;
    }
```

## getUploadedByAttribute

Visibilidad: `public`. Líneas originales 162–165.

```php
    public function getUploadedByAttribute(): ?string
    {
        return $this->attributes['subido_por'] ?? null;
    }
```

## getCreatedAtAttribute

Visibilidad: `public`. Líneas originales 167–170.

```php
    public function getCreatedAtAttribute()
    {
        return $this->creado_en;
    }
```

## getUpdatedAtAttribute

Visibilidad: `public`. Líneas originales 172–175.

```php
    public function getUpdatedAtAttribute()
    {
        return $this->actualizado_en;
    }
```

## getVisibilidadAttribute

Visibilidad: `public`. Líneas originales 177–184.

```php
    public function getVisibilidadAttribute($value): string
    {
        return match ($value) {
            'publico' => self::VISIBILITY_PUBLIC,
            'privado' => self::VISIBILITY_PRIVATE,
            default => (string) $value,
        };
    }
```

## setVisibilidadAttribute

Visibilidad: `public`. Líneas originales 186–189.

```php
    public function setVisibilidadAttribute($value): void
    {
        $this->attributes['visibilidad'] = $this->mapLegacyValue('visibility', $value);
    }
```

## getCategoriaAttribute

Visibilidad: `public`. Líneas originales 191–201.

```php
    public function getCategoriaAttribute($value): string
    {
        if (!empty($this->attributes['categoria_detalle'])) return $this->attributes['categoria_detalle'];
        return match ($value) {
            'repositorio' => self::CATEGORY_REPOSITORY,
            'entregable' => self::CATEGORY_DELIVERABLE,
            'evaluacion' => self::CATEGORY_EVALUATION_DOCUMENT,
            'tesis' => self::CATEGORY_THESIS_GENERAL,
            default => (string) $value,
        };
    }
```

## setCategoriaAttribute

Visibilidad: `public`. Líneas originales 203–210.

```php
    public function setCategoriaAttribute($value): void
    {
        $detail = in_array($value, [self::CATEGORY_EVALUATION_RELEASE, self::CATEGORY_EVALUATION_PRESENTATION, self::CATEGORY_THESIS_RESIDENCY], true);
        if (\Illuminate\Support\Facades\Schema::hasColumn('documentos', 'categoria_detalle')) {
            $this->attributes['categoria_detalle'] = $detail ? $value : null;
            $this->attributes['categoria'] = $detail ? ($value === self::CATEGORY_THESIS_RESIDENCY ? 'tesis' : 'evaluacion') : $this->mapLegacyValue('document_category', $value);
        } else $this->attributes['categoria'] = $this->mapLegacyValue('document_category', $value);
    }
```

## mapLegacyValue

Visibilidad: `public`. Líneas originales 212–238.

```php
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
```

## getArchivoPathAttribute

Visibilidad: `public`. Líneas originales 240–243.

```php
    public function getArchivoPathAttribute(): ?string
    {
        return $this->latestFileValue('ruta_archivo');
    }
```

## getArchivoTipoAttribute

Visibilidad: `public`. Líneas originales 245–248.

```php
    public function getArchivoTipoAttribute(): ?string
    {
        return $this->latestFileValue('extension');
    }
```

## getFileAvailableAttribute

Visibilidad: `public`. Líneas originales 250–270.

```php
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
```

## getAutoresAttribute

Visibilidad: `public`. Líneas originales 272–275.

```php
    public function getAutoresAttribute(): string
    {
        return (string) ($this->autor_nombre ?? '');
    }
```

## setAutoresAttribute

Visibilidad: `public`. Líneas originales 277–285.

```php
    public function setAutoresAttribute(?string $value): void
    {
        $this->pendingAuthors = collect(explode(',', (string) $value))
            ->map(fn ($author) => trim($author))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
```

## booted

Visibilidad: `protected`. Líneas originales 287–296.

```php
    protected static function booted(): void
    {
        static::saving(function (RepositoryDocument $document) {
            if ($document->pendingAuthors !== null) {
                $document->autor_nombre = collect($document->pendingAuthors)->join(', ');
                $document->pendingAuthors = null;
            }
        });

    }
```

## latestFileValue

Visibilidad: `private`. Líneas originales 298–305.

```php
    private function latestFileValue(string $column): ?string
    {
        $version = $this->relationLoaded('latestVersion')
            ? $this->getRelation('latestVersion')
            : $this->latestVersion()->first();

        return $version?->getRawOriginal($column);
    }
```
