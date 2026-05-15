<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avance extends Model
{
    use HasFactory;

    protected $table = 'avances';
    public $timestamps = false;

    protected $fillable = ['project_id', 'descripcion', 'porcentaje', 'reportado_por'];

    protected $casts = [
        'porcentaje' => 'decimal:2',
    ];

    // RELACIONES
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reportado_por', 'id')->where('activo', true);
    }
}
