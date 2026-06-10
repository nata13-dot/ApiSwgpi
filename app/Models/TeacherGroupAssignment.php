<?php

namespace App\Models;

use App\Models\Concerns\HasLegacyAliases;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherGroupAssignment extends Model
{
    use HasFactory, HasLegacyAliases;

    protected $table = 'asignaciones_docentes_grupos';
    const CREATED_AT = 'creado_en';
    const UPDATED_AT = 'actualizado_en';

    protected array $legacyAliases = [
        'subject_group_id' => 'grupo_academico_id',
        'teacher_id' => 'docente_id',
        'created_at' => 'creado_en',
        'updated_at' => 'actualizado_en',
    ];

    protected $fillable = ['subject_group_id', 'asignatura_id', 'teacher_id', 'labor', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'asignatura_id' => 'integer',
    ];

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class, 'grupo_academico_id');
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id', 'id')->where('activo', true);
    }
}
