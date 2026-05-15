<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';
    public $timestamps = false;

    protected $fillable = ['deliverable_id', 'comentario', 'comentado_por', 'estado'];

    // RELACIONES
    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class, 'deliverable_id');
    }

    public function commentedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comentado_por', 'id')->where('activo', true);
    }

    // SCOPES
    public function scopePendiente($query) { return $query->where('estado', 'pendiente'); }
    public function scopeRevisado($query) { return $query->where('estado', 'revisado'); }
}
