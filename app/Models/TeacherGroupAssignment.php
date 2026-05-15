<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherGroupAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['subject_group_id', 'asignatura_id', 'teacher_id', 'labor', 'activo'];

    protected $casts = [
        'activo' => 'boolean',
        'asignatura_id' => 'integer',
    ];

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class);
    }

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'id')->where('activo', true);
    }
}
