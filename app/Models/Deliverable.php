<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deliverable extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'entregables_proyecto';
    public $timestamps = false;

    protected array $legacyAliases = [
        'project_id' => 'proyecto_id',
        'archivo_path' => 'archivo_ruta',
        'file_path' => 'archivo_ruta',
        'submitted_by' => 'enviado_por',
        'created_at' => 'creado_en',
    ];

    protected array $legacyVirtualColumns = ['categoria', 'autores', 'calificacion', 'fecha_calificacion', 'calificado_por'];

    protected $fillable = [
        'project_id', 'competencia_id', 'categoria', 'nombre', 'descripcion', 'autores',
        'tipo_documento', 'rama_asociada', 'estado', 'archivo_path', 'submitted_by', 'activo',
        'calificacion', 'fecha_calificacion', 'calificado_por'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
    ];

    // RELACIONES
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por', 'id')->where('activo', true);
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
