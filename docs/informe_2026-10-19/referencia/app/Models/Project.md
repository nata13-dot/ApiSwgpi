# Project

Fuente: [app/Models/Project.php](../../../../../app/Models/Project.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use App\Models\Pivots\ProjectMemberPivot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToCareer, HasFactory, HasLegacyAliases;

    protected $table = 'proyectos';

    public $timestamps = true;

    const CREATED_AT = 'creado_en';

    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'title' => 'titulo',
        'description' => 'descripcion',
        'created_by' => 'creado_por',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
        'subject_group_id' => 'grupo_id',
        'proposal_status' => 'estado',
        'proposal_reviewed_by' => 'revisado_por',
        'proposal_review_comment' => 'comentario_revision',
        'proposal_reviewed_at' => 'revisado_en',
        'revision_allowed_until' => 'revision_permitida_hasta',
    ];

    protected array $legacyVirtualColumns = [
        'is_thesis',
        'is_proposal',
        'semestre',
        'year',
        'authors',
        'company_name',
        'company_giro',
        'company_contact_name',
        'company_contact_position',
        'company_address',
        'company_rfc',
    ];

    protected $appends = [
        'title',
        'description',
        'created_by',
        'created_at',
        'updated_at',
        'subject_group_id',
        'is_thesis',
        'is_proposal',
        'proposal_status',
        'proposal_reviewed_by',
        'proposal_review_comment',
        'proposal_reviewed_at',
        'revision_allowed_until',
        'semestre',
        'year',
        'authors',
        'company_name',
        'company_giro',
        'company_contact_name',
        'company_contact_position',
        'company_address',
        'company_rfc',
    ];

    protected $fillable = ['career_id', 'title', 'description', 'created_by', 'activo', 'tipo', 'modalidad', 'is_thesis', 'is_proposal', 'subject_group_id', 'empresa_id', 'proposal_status', 'proposal_reviewed_by', 'proposal_review_comment', 'proposal_reviewed_at', 'revision_allowed_until'];

    protected $casts = [
        'activo' => 'boolean',
        'es_tesis' => 'boolean',
        'es_propuesta' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'grupo_id' => 'integer',
        'revisado_en' => 'datetime',
        'revision_permitida_hasta' => 'datetime',
    ];

    // RELACIONES












    // MÉTODOS

    /**
     * Obtener solo los estudiantes del proyecto (sin rol_asesor)
     */

    /**
     * Obtener solo los asesores del proyecto (con rol_asesor)
     */






























    // SCOPES


}
```

## creator

Visibilidad: `public`. Líneas originales 93–96.

```php
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'id')->where('activo', true);
    }
```

## advisors

Visibilidad: `public`. Líneas originales 98–105.

```php
    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
            ->using(ProjectMemberPivot::class)
            ->withoutGlobalScope('careerMembership')
            ->wherePivotNotIn('rol', ['lider', 'integrante'])
            ->withPivot('rol');
    }
```

## proposalReviewer

Visibilidad: `public`. Líneas originales 107–110.

```php
    public function proposalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por', 'id')->where('activo', true);
    }
```

## subjectGroup

Visibilidad: `public`. Líneas originales 112–115.

```php
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_id');
    }
```

## asignaturas

Visibilidad: `public`. Líneas originales 117–121.

```php
    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'cursos', 'grupo_id', 'asignatura_id', 'grupo_id', 'id')
            ->wherePivot('activo', true);
    }
```

## deliverables

Visibilidad: `public`. Líneas originales 123–127.

```php
    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'entregas', 'proyecto_id', 'entregable_id')
            ->withPivot(['documento_id', 'enviado_por', 'entregado_en', 'calificacion', 'comentarios_docente']);
    }
```

## deliveries

Visibilidad: `public`. Líneas originales 129–132.

```php
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'proyecto_id');
    }
```

## repositoryDocuments

Visibilidad: `public`. Líneas originales 134–137.

```php
    public function repositoryDocuments(): HasMany
    {
        return $this->hasMany(RepositoryDocument::class, 'proyecto_id');
    }
```

## evaluations

Visibilidad: `public`. Líneas originales 139–142.

```php
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'proyecto_id');
    }
```

## avances

Visibilidad: `public`. Líneas originales 144–147.

```php
    public function avances(): HasMany
    {
        return $this->hasMany(Avance::class, 'proyecto_id');
    }
```

## feedbacks

Visibilidad: `public`. Líneas originales 149–152.

```php
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'project_id');
    }
```

## grafos

Visibilidad: `public`. Líneas originales 154–157.

```php
    public function grafos(): HasMany
    {
        return $this->hasMany(Grafo::class, 'project_id');
    }
```

## isActive

Visibilidad: `public`. Líneas originales 160–163.

```php
    public function isActive(): bool
    {
        return $this->activo === true;
    }
```

## students

Visibilidad: `public`. Líneas originales 168–175.

```php
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
            ->using(ProjectMemberPivot::class)
            ->withoutGlobalScope('careerMembership')
            ->wherePivotIn('rol', ['lider', 'integrante'])
            ->withPivot('rol');
    }
```

## onlyAdvisors

Visibilidad: `public`. Líneas originales 180–187.

```php
    public function onlyAdvisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
            ->using(ProjectMemberPivot::class)
            ->withoutGlobalScope('careerMembership')
            ->wherePivotNotIn('rol', ['lider', 'integrante'])
            ->withPivot('rol');
    }
```

## getAsesorTesis

Visibilidad: `public`. Líneas originales 189–194.

```php
    public function getAsesorTesis()
    {
        return $this->advisors()
            ->wherePivot('rol', 'asesor')
            ->first();
    }
```

## getRevisorUno

Visibilidad: `public`. Líneas originales 196–201.

```php
    public function getRevisorUno()
    {
        return $this->advisors()
            ->wherePivot('rol', 'revisor_1')
            ->first();
    }
```

## getRevisorDos

Visibilidad: `public`. Líneas originales 203–208.

```php
    public function getRevisorDos()
    {
        return $this->advisors()
            ->wherePivot('rol', 'revisor_2')
            ->first();
    }
```

## getAsesorPrimario

Visibilidad: `public`. Líneas originales 210–215.

```php
    public function getAsesorPrimario()
    {
        return $this->advisors()
            ->wherePivot('rol', 'primario')
            ->first();
    }
```

## getAsesorSecundario

Visibilidad: `public`. Líneas originales 217–222.

```php
    public function getAsesorSecundario()
    {
        return $this->advisors()
            ->wherePivot('rol', 'secundario')
            ->first();
    }
```

## getProgress

Visibilidad: `public`. Líneas originales 224–234.

```php
    public function getProgress(): float
    {
        $total = $this->deliveries()->count();
        if ($total === 0) {
            return 0;
        }

        $approved = $this->deliveries()->where('calificacion', '>=', 70)->count();

        return ($approved / $total) * 100;
    }
```

## getTitleAttribute

Visibilidad: `public`. Líneas originales 236–239.

```php
    public function getTitleAttribute(): ?string
    {
        return $this->titulo;
    }
```

## getDescriptionAttribute

Visibilidad: `public`. Líneas originales 241–244.

```php
    public function getDescriptionAttribute(): ?string
    {
        return $this->descripcion;
    }
```

## getCreatedByAttribute

Visibilidad: `public`. Líneas originales 246–249.

```php
    public function getCreatedByAttribute(): ?string
    {
        return $this->creado_por;
    }
```

## getCreatedAtAttribute

Visibilidad: `public`. Líneas originales 251–254.

```php
    public function getCreatedAtAttribute()
    {
        return $this->creado_en;
    }
```

## getUpdatedAtAttribute

Visibilidad: `public`. Líneas originales 256–259.

```php
    public function getUpdatedAtAttribute()
    {
        return $this->actualizado_en;
    }
```

## getSubjectGroupIdAttribute

Visibilidad: `public`. Líneas originales 261–264.

```php
    public function getSubjectGroupIdAttribute(): ?int
    {
        return $this->grupo_id;
    }
```

## getIsThesisAttribute

Visibilidad: `public`. Líneas originales 266–269.

```php
    public function getIsThesisAttribute(): bool
    {
        return ($this->tipo ?? null) === 'tesis';
    }
```

## getIsProposalAttribute

Visibilidad: `public`. Líneas originales 271–274.

```php
    public function getIsProposalAttribute(): bool
    {
        return ($this->tipo ?? null) === 'propuesta';
    }
```

## getProposalStatusAttribute

Visibilidad: `public`. Líneas originales 276–279.

```php
    public function getProposalStatusAttribute(): ?string
    {
        return $this->estado;
    }
```

## getProposalReviewedByAttribute

Visibilidad: `public`. Líneas originales 281–284.

```php
    public function getProposalReviewedByAttribute(): ?string
    {
        return $this->revisado_por;
    }
```

## getProposalReviewCommentAttribute

Visibilidad: `public`. Líneas originales 286–289.

```php
    public function getProposalReviewCommentAttribute(): ?string
    {
        return $this->comentario_revision;
    }
```

## getProposalReviewedAtAttribute

Visibilidad: `public`. Líneas originales 291–294.

```php
    public function getProposalReviewedAtAttribute()
    {
        return $this->revisado_en;
    }
```

## getRevisionAllowedUntilAttribute

Visibilidad: `public`. Líneas originales 296–299.

```php
    public function getRevisionAllowedUntilAttribute()
    {
        return $this->revision_permitida_hasta;
    }
```

## empresa

Visibilidad: `public`. Líneas originales 301–304.

```php
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
```

## getSemestreAttribute

Visibilidad: `public`. Líneas originales 306–313.

```php
    public function getSemestreAttribute(): ?int
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('proyecto_periodos') && app(\App\Support\CareerContext::class)->careerId()) {
            $period = app(\App\Services\AcademicWorkflowService::class)->period();
            if ($period) return app(\App\Services\AcademicWorkflowService::class)->projectSemester($this, $period->id) ?? $this->subjectGroup?->semestre;
        }
        return $this->subjectGroup?->semestre;
    }
```

## getYearAttribute

Visibilidad: `public`. Líneas originales 315–318.

```php
    public function getYearAttribute(): ?int
    {
        return $this->creado_en?->year;
    }
```

## getAuthorsAttribute

Visibilidad: `public`. Líneas originales 320–326.

```php
    public function getAuthorsAttribute(): string
    {
        return $this->students
            ->map(fn (User $student) => $student->getFullName())
            ->filter()
            ->join(', ');
    }
```

## getCompanyNameAttribute

Visibilidad: `public`. Líneas originales 328–331.

```php
    public function getCompanyNameAttribute(): ?string
    {
        return $this->empresa?->nombre;
    }
```

## getCompanyGiroAttribute

Visibilidad: `public`. Líneas originales 333–336.

```php
    public function getCompanyGiroAttribute(): ?string
    {
        return $this->empresa?->giro;
    }
```

## getCompanyContactNameAttribute

Visibilidad: `public`. Líneas originales 338–341.

```php
    public function getCompanyContactNameAttribute(): ?string
    {
        return $this->empresa?->contacto_nombre;
    }
```

## getCompanyContactPositionAttribute

Visibilidad: `public`. Líneas originales 343–346.

```php
    public function getCompanyContactPositionAttribute(): ?string
    {
        return $this->empresa?->contacto_cargo;
    }
```

## getCompanyAddressAttribute

Visibilidad: `public`. Líneas originales 348–351.

```php
    public function getCompanyAddressAttribute(): ?string
    {
        return $this->empresa?->direccion;
    }
```

## getCompanyRfcAttribute

Visibilidad: `public`. Líneas originales 353–356.

```php
    public function getCompanyRfcAttribute(): ?string
    {
        return $this->empresa?->rfc;
    }
```

## scopeActivos

Visibilidad: `public`. Líneas originales 359–362.

```php
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
```

## scopeInactivos

Visibilidad: `public`. Líneas originales 364–367.

```php
    public function scopeInactivos($query)
    {
        return $query->where('activo', false);
    }
```

## scopeSearch

Visibilidad: `public`. Líneas originales 369–373.

```php
    public function scopeSearch($query, $term)
    {
        return $query->where('title', 'like', "%{$term}%")
            ->orWhere('description', 'like', "%{$term}%");
    }
```
