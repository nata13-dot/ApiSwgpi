<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRegistrationWindow extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'ventanas_registro_proyectos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'starts_at' => 'inicia_en',
        'ends_at' => 'termina_en',
        'notes' => 'notas',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $appends = ['subject_group_id', 'starts_at', 'ends_at', 'notes'];

    protected $hidden = [
        'grupo_academico_id',
        'inicia_en',
        'termina_en',
        'notas',
        'creado_en',
        'actualizado_en',
    ];

    protected $fillable = ['subject_group_id', 'starts_at', 'ends_at', 'activo', 'notes'];

    protected $casts = [
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'activo' => 'boolean',
    ];

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }

    public function getSubjectGroupIdAttribute(): ?int
    {
        $value = $this->attributes['grupo_academico_id'] ?? null;
        return $value === null ? null : (int) $value;
    }

    public function getStartsAtAttribute(): mixed
    {
        $value = $this->attributes['inicia_en'] ?? null;
        return $value === null ? null : $this->asDateTime($value);
    }

    public function getEndsAtAttribute(): mixed
    {
        $value = $this->attributes['termina_en'] ?? null;
        return $value === null ? null : $this->asDateTime($value);
    }

    public function getNotesAttribute(): ?string
    {
        return $this->attributes['notas'] ?? null;
    }
}
