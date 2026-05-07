<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asignatura extends Model
{
    use HasFactory;

    protected $table = 'asignaturas';
    public $timestamps = false;

    protected $fillable = ['nombre', 'descripcion', 'numero_creditos', 'codigo', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'numero_creditos' => 'integer',
    ];

    // RELACIONES
    public function competencias(): HasMany
    {
        return $this->hasMany(Competencia::class, 'asignatura_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_asignatura', 'asignatura_id', 'project_id');
    }

    // SCOPES
    public function scopeActivas($query) { return $query->where('activo', true); }
}
