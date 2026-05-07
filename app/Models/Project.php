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

    protected $table = 'projects';
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $fillable = ['title', 'description', 'created_by', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
    ];

    // RELACIONES
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function advisors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id')
                    ->withPivot('rol_asesor');
    }

    public function asignaturas(): BelongsToMany
    {
        return $this->belongsToMany(Asignatura::class, 'project_asignatura', 'project_id', 'asignatura_id');
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class, 'project_id');
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
