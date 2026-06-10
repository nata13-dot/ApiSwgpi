<?php

namespace App\Models\Concerns;

use App\Models\Concerns\LegacyAliasBuilder;
use Illuminate\Database\Eloquent\Builder;

trait HasLegacyAliases
{
    public function newEloquentBuilder($query): Builder
    {
        return new LegacyAliasBuilder($query);
    }

    public function getAttribute($key)
    {
        if (isset($this->legacyAliases[$key])) {
            return parent::getAttribute($this->legacyAliases[$key]);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (isset($this->legacyAliases[$key])) {
            $key = $this->legacyAliases[$key];
        }

        return parent::setAttribute($key, $value);
    }

    public function scopeWhereLegacy($query, string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
    {
        $column = $this->legacyAliases[$column] ?? $column;

        if (func_num_args() === 3) {
            return $query->where($column, $operator, null, $boolean);
        }

        return $query->where($column, $operator, $value, $boolean);
    }

    public function getLegacyAliases(): array
    {
        return $this->legacyAliases ?? [];
    }

    public function getLegacyVirtualColumns(): array
    {
        return $this->legacyVirtualColumns ?? [];
    }
}
