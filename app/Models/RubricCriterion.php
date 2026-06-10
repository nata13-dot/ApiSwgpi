<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    use HasLegacyAliases;

    protected $table = 'criterios_rubrica';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'project_id' => 'proyecto_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = [
        'semestre', 'project_id', 'clave', 'pregunta', 'orden', 'activo',
    ];

    protected $casts = [
        'semestre' => 'integer',
        'project_id' => 'integer',
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'proyecto_id');
    }
}
