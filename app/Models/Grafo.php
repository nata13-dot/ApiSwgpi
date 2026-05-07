<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grafo extends Model
{
    use HasFactory;

    protected $table = 'grafos';
    public $timestamps = false;

    protected $fillable = ['project_id', 'datos', 'descripcion'];

    // RELACIONES
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
