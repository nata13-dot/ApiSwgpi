<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCareer;
use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deliverable extends Model
{
    use HasFactory, HasLegacyAliases, BelongsToCareer;

    protected $table = 'entregables';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = null;

    protected array $legacyAliases = [
        'created_at' => 'creado_en',
    ];

    protected array $legacyVirtualColumns = [
        'project_id',
        'competencia_id',
        'categoria',
        'autores',
        'archivo_path',
        'file_path',
        'submitted_by',
        'calificacion',
        'fecha_calificacion',
        'calificado_por',
        'rama_asociada',
    ];

    protected $fillable = [
        'carrera_id', 'curso_id', 'nombre', 'descripcion', 'tipo_documento', 'fecha_limite', 'estado', 'activo',
        'project_id', 'competencia_id', 'categoria', 'autores', 'archivo_path', 'submitted_by',
        'calificacion', 'fecha_calificacion', 'calificado_por', 'rama_asociada',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
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
        return $this->belongsTo(User::class, 'submitted_by', 'id')->where('activo', true);
    }

    public function calificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calificado_por', 'id')->where('activo', true);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'entregables_etiquetas', 'entregable_proyecto_id', 'etiqueta_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'entregable_proyecto_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'deliverable_id');
    }

    // SCOPES
    public function scopeActivos($query) { return $query->where('activo', true); }
    public function scopeEstado($query, $estado) { return $query->where('estado', $estado); }
    public function scopeByProject($query, $projectId) { return $query->where('proyecto_id', $projectId); }
}
