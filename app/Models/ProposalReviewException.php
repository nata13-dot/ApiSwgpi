<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalReviewException extends Model
{
    protected $table = 'excepciones_revision_propuesta';

    protected $fillable = [
        'asignatura_id', 'subject_group_id', 'teacher_id', 'student_id', 'notes', 'activo',
    ];

    protected $casts = [
        'asignatura_id' => 'integer',
        'subject_group_id' => 'integer',
        'activo' => 'boolean',
    ];

    public function asignatura(): BelongsTo
    {
        return $this->belongsTo(Asignatura::class);
    }

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id', 'id')->where('activo', true);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id', 'id')->where('activo', true);
    }
}
