<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRegistrationWindow extends Model
{
    use HasFactory;

    protected $fillable = ['subject_group_id', 'starts_at', 'ends_at', 'activo', 'notes'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'activo' => 'boolean',
    ];

    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class);
    }
}