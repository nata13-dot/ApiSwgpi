<?php

namespace App\Models\Concerns;

use App\Models\Career;
use App\Support\CareerContext;
use App\Support\CareerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Las tablas raíz guardan carrera_id; las derivadas (CareerScope::PATHS) la obtienen de su padre,
 * y documentos sólo la guarda cuando no tiene proyecto.
 */
trait BelongsToCareer
{
    private ?int $derivedCareerId = null;

    protected static function bootBelongsToCareer(): void
    {
        static::addGlobalScope('career', function (Builder $query): void {
            $careerId = app(CareerContext::class)->careerId();
            if ($careerId) {
                $query->getModel()->constrainToCareer($query, $careerId);
            }
        });

        static::creating(function ($model): void {
            if (!$model->derivesCareer() && empty($model->getAttributes()['carrera_id'])) {
                $model->attributes['carrera_id'] = app(CareerContext::class)->careerId();
            }
        });
    }

    public function derivesCareer(): bool
    {
        return isset(CareerScope::PATHS[$this->getTable()]);
    }

    public function constrainToCareer(Builder $query, int $careerId): Builder
    {
        if ($this->getTable() === 'documentos') {
            return CareerScope::constrainOptional($query, 'documentos', $careerId);
        }

        return CareerScope::constrain($query, $this->getTable(), $careerId);
    }

    public function getCarreraIdAttribute($value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }
        if (!$this->derivesCareer() && $this->getTable() !== 'documentos') {
            return null;
        }

        return $this->derivedCareerId ??= $this->resolveDerivedCareer();
    }

    public function setCarreraIdAttribute($value): void
    {
        // En las tablas derivadas la carrera es la del padre y no se guarda.
        if (!$this->derivesCareer()) {
            $this->attributes['carrera_id'] = $value;
        }
    }

    private function resolveDerivedCareer(): ?int
    {
        [$column, $parent] = CareerScope::PATHS[$this->getTable()] ?? ['proyecto_id', 'proyectos'];
        $parentId = $this->attributes[$column] ?? null;
        if (!$parentId) {
            return null;
        }
        $career = DB::table("{$parent} as padre")->where('padre.id', $parentId)
            ->value(DB::raw(CareerScope::expression($parent, 'padre')));

        return $career === null ? null : (int) $career;
    }

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class, 'carrera_id');
    }

    public function scopeForCareer(Builder $query, int $careerId): Builder
    {
        return $this->constrainToCareer($query->withoutGlobalScope('career'), $careerId);
    }
}
