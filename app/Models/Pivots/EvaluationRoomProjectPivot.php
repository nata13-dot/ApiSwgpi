<?php

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EvaluationRoomProjectPivot extends Pivot
{
    public function getPresentationOrderAttribute(): int
    {
        return (int) ($this->orden ?? 0);
    }

    public function setPresentationOrderAttribute(int $value): void
    {
        $this->attributes['orden'] = $value;
    }

    public function getStatusAttribute(): string
    {
        return 'pendiente';
    }
}
