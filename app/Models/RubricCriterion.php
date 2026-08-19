<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'rubrica_id', 'semestre', 'project_id', 'clave', 'pregunta', 'orden', 'activo',
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

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'rubrica_id');
    }

    public function getSemestreAttribute(): ?int
    {
        if (array_key_exists('semestre', $this->attributes)) {
            return $this->attributes['semestre'] === null ? null : (int) $this->attributes['semestre'];
        }

        $value = \Illuminate\Support\Facades\DB::table('rubricas')
            ->where('id', $this->rubrica_id)
            ->value('semestre');

        return $value === null ? null : (int) $value;
    }
}
