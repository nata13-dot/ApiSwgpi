<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory;

    protected $table = 'proyectos';
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = ['title', 'description', 'created_by', 'activo', 'is_thesis', 'semestre', 'subject_group_id', 'year', 'file_path', 'authors', 'company_name', 'company_giro', 'company_contact_name', 'company_contact_position', 'company_address', 'proposal_status', 'proposal_reviewed_by', 'proposal_review_comment', 'proposal_reviewed_at', 'revision_allowed_until'];

    protected $casts = [
        'activo' => 'boolean',
        'is_thesis' => 'boolean',
        'created_at' => 'datetime',
        'year' => 'integer',
        'semestre' => 'integer',
        'subject_group_id' => 'integer',
        'proposal_reviewed_at' => 'datetime',
        'revision_allowed_until' => 'datetime',
    ];

    // RELACIONES
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id')->where('activo', true);
    }

    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyectos_integrantes', 'project_id', 'user_id')
                    ->where('usuarios.activo', true)
                    ->wherePivotNotNull('rol_asesor')
                    ->withPivot('rol_asesor');
    }

    public function proposalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposal_reviewed_by', 'id')->where('activo', true);
    }

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'subject_group_id');
    }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'proyectos_asignaturas', 'project_id', 'asignatura_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'project_id');
    }

    public function repositoryDocuments(): HasMany
    {
        return $this->hasMany(RepositoryDocument::class, 'project_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class, 'project_id');
    }

    public function avances(): HasMany
    {
        return $this->hasMany(Avance::class, 'project_id');
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
        return $this->belongsToMany(User::class, 'proyectos_integrantes', 'project_id', 'user_id')
                    ->where('usuarios.activo', true)
                    ->whereNull('rol_asesor')
                    ->withPivot('rol_asesor');
    }
    
    /**
     * Obtener solo los asesores del proyecto (con rol_asesor)
     */
    public function onlyAdvisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyectos_integrantes', 'project_id', 'user_id')
                    ->where('usuarios.activo', true)
                    ->wherePivotNotNull('rol_asesor')
                    ->withPivot('rol_asesor');
    }
    
    public function getAsesorTesis()
    {
        return $this->advisors()
            ->wherePivot('rol_asesor', 'asesor')
            ->first();
    }

    public function getRevisorUno()
    {
        return $this->advisors()
            ->wherePivot('rol_asesor', 'revisor_1')
            ->first();
    }

    public function getRevisorDos()
    {
        return $this->advisors()
            ->wherePivot('rol_asesor', 'revisor_2')
            ->first();
    }

    public function getAsesorPrimario()
    {
        return $this->advisors()
            ->wherePivot('rol_asesor', 'primario')
            ->first();
    }

    public function getAsesorSecundario()
    {
        return $this->advisors()
            ->wherePivot('rol_asesor', 'secundario')
            ->first();
    }
    
    public function getProgress(): float
    {
        $total = $this->deliverables()->count();
        if ($total === 0) return 0;
        
        $approved = $this->deliverables()->where('estado', 'aprobado')->count();
        return ($approved / $total) * 100;
    }

    // SCOPES
    public function scopeActivos($query) { return $query->where('activo', true); }
    public function scopeInactivos($query) { return $query->where('activo', false); }
    public function scopeSearch($query, $term)
    {
        return $query->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
    }
}
