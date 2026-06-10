<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectMemberPivot extends Pivot
{
    protected $appends = [
        'rol_asesor',
    ];

    public function getRolAsesorAttribute(): ?string
    {
        return ($this->rol ?? null) === 'integrante' ? null : $this->rol;
    }

    public function setRolAsesorAttribute(?string $value): void
    {
        $this->attributes['rol'] = $value ?: 'integrante';
    }
}
