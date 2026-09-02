<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use App\Models\Concerns\BelongsToCareer;
use App\Models\Pivots\ProjectMemberPivot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory, HasLegacyAliases, BelongsToCareer;

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
        'file_path',
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
        'file_path',
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

    protected $fillable = ['career_id', 'title', 'description', 'created_by', 'activo', 'tipo', 'modalidad', 'is_thesis', 'is_proposal', 'subject_group_id', 'empresa_id', 'file_path', 'proposal_status', 'proposal_reviewed_by', 'proposal_review_comment', 'proposal_reviewed_at', 'revision_allowed_until'];

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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'id')->where('activo', true);
    }

    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
                    ->using(ProjectMemberPivot::class)
                    ->where('usuarios.activo', true)
                    ->wherePivotNotIn('rol', ['lider', 'integrante'])
                    ->withPivot('rol');
    }

    public function proposalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por', 'id')->where('activo', true);
    }

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_id');
    }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'cursos', 'grupo_id', 'asignatura_id', 'grupo_id', 'id')
            ->wherePivot('activo', true);
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(Deliverable::class, 'entregas', 'proyecto_id', 'entregable_id')
            ->withPivot(['documento_id', 'enviado_por', 'entregado_en', 'calificacion', 'comentarios_docente']);
    }

    public function repositoryDocuments(): HasMany
    {
        return $this->hasMany(RepositoryDocument::class, 'proyecto_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'proyecto_id');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(Avance::class, 'proyecto_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'project_id');
    }

    public function grafos(): HasMany
    {
        return $this->hasMany(Grafo::class, 'project_id');
    }

    // MÉTODOS
    public function isActive(): bool { return $this->activo === true; }
    
    /**
     * Obtener solo los estudiantes del proyecto (sin rol_asesor)
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
                    ->using(ProjectMemberPivot::class)
                    ->where('usuarios.activo', true)
                    ->wherePivotIn('rol', ['lider', 'integrante'])
                    ->withPivot('rol');
    }
    
    /**
     * Obtener solo los asesores del proyecto (con rol_asesor)
     */
    public function onlyAdvisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_integrantes', 'proyecto_id', 'usuario_id')
                    ->using(ProjectMemberPivot::class)
                    ->where('usuarios.activo', true)
                    ->wherePivotNotIn('rol', ['lider', 'integrante'])
                    ->withPivot('rol');
    }
    
    public function getAsesorTesis()
    {
        return $this->advisors()
            ->wherePivot('rol', 'asesor')
            ->first();
    }

    public function getRevisorUno()
    {
        return $this->advisors()
            ->wherePivot('rol', 'revisor_1')
            ->first();
    }

    public function getRevisorDos()
    {
        return $this->advisors()
            ->wherePivot('rol', 'revisor_2')
            ->first();
    }

    public function getAsesorPrimario()
    {
        return $this->advisors()
            ->wherePivot('rol', 'primario')
            ->first();
    }

    public function getAsesorSecundario()
    {
        return $this->advisors()
            ->wherePivot('rol', 'secundario')
            ->first();
    }
    
    public function getProgress(): float
    {
        $total = $this->deliverables()->count();
        if ($total === 0) return 0;
        
        $approved = $this->deliverables()->where('estado', 'aprobado')->count();
        return ($approved / $total) * 100;
    }

    public function getTitleAttribute(): ?string { return $this->titulo; }
    public function getDescriptionAttribute(): ?string { return $this->descripcion; }
    public function getCreatedByAttribute(): ?string { return $this->creado_por; }
    public function getCreatedAtAttribute() { return $this->creado_en; }
    public function getUpdatedAtAttribute() { return $this->actualizado_en; }
    public function getSubjectGroupIdAttribute(): ?int { return $this->grupo_id; }
    public function getFilePathAttribute(): ?string { return $this->attributes['archivo_ruta'] ?? null; }
    public function getIsThesisAttribute(): bool { return ($this->tipo ?? null) === 'tesis'; }
    public function getIsProposalAttribute(): bool { return ($this->tipo ?? null) === 'propuesta'; }
    public function getProposalStatusAttribute(): ?string { return $this->estado; }
    public function getProposalReviewedByAttribute(): ?string { return $this->revisado_por; }
    public function getProposalReviewCommentAttribute(): ?string { return $this->comentario_revision; }
    public function getProposalReviewedAtAttribute() { return $this->revisado_en; }
    public function getRevisionAllowedUntilAttribute() { return $this->revision_permitida_hasta; }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function getSemestreAttribute(): ?int
    {
        return $this->subjectGroup?->semestre;
    }

    public function getYearAttribute(): ?int
    {
        return $this->creado_en?->year;
    }

    public function getAuthorsAttribute(): string
    {
        return $this->students
            ->map(fn (User $student) => $student->getFullName())
            ->filter()
            ->join(', ');
    }

    public function getCompanyNameAttribute(): ?string { return $this->empresa?->nombre; }
    public function getCompanyGiroAttribute(): ?string { return $this->empresa?->giro; }
    public function getCompanyContactNameAttribute(): ?string { return $this->empresa?->contacto_nombre; }
    public function getCompanyContactPositionAttribute(): ?string { return $this->empresa?->contacto_cargo; }
    public function getCompanyAddressAttribute(): ?string { return $this->empresa?->direccion; }
    public function getCompanyRfcAttribute(): ?string { return $this->empresa?->rfc; }

    // SCOPES
    public function scopeActivos($query) { return $query->where('activo', true); }
    public function scopeInactivos($query) { return $query->where('activo', false); }
    public function scopeSearch($query, $term)
    {
        return $query->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
    }
}
