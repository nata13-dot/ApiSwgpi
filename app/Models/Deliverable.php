<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deliverable extends Model
{
    use HasFactory;

    protected $table = 'deliverables';
    public $timestamps = false;

    protected $fillable = [
        'project_id', 'competencia_id', 'nombre', 'descripcion', 'autores',
        'tipo_documento', 'rama_asociada', 'estado', 'archivo_path', 'submitted_by', 'activo',
        'calificacion', 'fecha_calificacion', 'calificado_por'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'calificacion' => 'float',
        'fecha_calificacion' => 'datetime',
    ];

    // RELACIONES
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by', 'id');
    }

    public function calificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calificado_por', 'id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'deliverable_document_tag', 'deliverable_id', 'document_tag_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'deliverable_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'deliverable_id');
    }

    // SCOPES
    public function scopeActivos($query) { return $query->where('activo', true); }
    public function scopeEstado($query, $estado) { return $query->where('estado', $estado); }
    public function scopeByProject($query, $projectId) { return $query->where('project_id', $projectId); }
}
