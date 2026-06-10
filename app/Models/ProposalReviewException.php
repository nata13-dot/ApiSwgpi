<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalReviewException extends Model
{
    use HasLegacyAliases;

    protected $table = 'excepciones_revision_propuesta';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'project_id' => 'proyecto_id',
        'teacher_id' => 'docente_id',
        'student_id' => 'estudiante_id',
        'notes' => 'motivo',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = [
        'asignatura_id', 'subject_group_id', 'teacher_id', 'student_id', 'notes', 'activo',
    ];

    protected $appends = ['subject_group_id', 'teacher_id', 'student_id', 'notes'];

    protected $hidden = [
        'grupo_academico_id',
        'docente_id',
        'estudiante_id',
        'motivo',
    ];

    protected $casts = [
        'asignatura_id' => 'integer',
        'grupo_academico_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id')->where('activo', true);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'estudiante_id', 'id')->where('activo', true);
    }

    public function getSubjectGroupIdAttribute(): ?int
    {
        $value = $this->attributes['grupo_academico_id'] ?? null;
        return $value === null ? null : (int) $value;
    }

    public function getTeacherIdAttribute(): ?string
    {
        return $this->attributes['docente_id'] ?? null;
    }

    public function getStudentIdAttribute(): ?string
    {
        return $this->attributes['estudiante_id'] ?? null;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->attributes['motivo'] ?? null;
    }
}
