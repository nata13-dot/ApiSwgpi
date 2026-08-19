<?php

namespace App\Models\Concerns;

use App\Models\Career;
use App\Support\CareerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCareer
{
    protected static function bootBelongsToCareer(): void
    {
        static::addGlobalScope('career', function (Builder $query): void {
            $careerId = app(CareerContext::class)->careerId();
            if ($careerId) {
                $query->where($query->getModel()->qualifyColumn('carrera_id'), $careerId);
            }
        });

        static::creating(function ($model): void {
            if (!$model->carrera_id) {
                $model->carrera_id = app(CareerContext::class)->careerId();
            }
        });
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'carrera_id');
    }

    public function scopeForCareer(Builder $query, int $careerId): Builder
    {
        return $query->withoutGlobalScope('career')->where(
            $query->getModel()->qualifyColumn('carrera_id'),
            $careerId
        );
    }
}

