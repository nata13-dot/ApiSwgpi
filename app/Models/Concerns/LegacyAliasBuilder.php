<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class LegacyAliasBuilder extends Builder
{
    public function select($columns = ['*'])
    {
        return parent::select($this->mapColumns(is_array($columns) ? $columns : func_get_args()));
    }

    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();

        return parent::addSelect($this->mapColumns($columns));
    }

    public function get($columns = ['*'])
    {
        return parent::get($this->mapColumns((array) $columns));
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return parent::paginate($perPage, $this->mapColumns((array) $columns), $pageName, $page, $total);
    }

    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return parent::simplePaginate($perPage, $this->mapColumns((array) $columns), $pageName, $page);
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            if (func_num_args() === 2) {
                $operator = $this->mapValue($column, $operator);
            } else {
                $value = $this->mapValue($column, $value);
            }
        }

        return parent::where($this->mapWhereColumn($column), $operator, $value, $boolean);
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_string($column)) {
            if (func_num_args() === 2) {
                $operator = $this->mapValue($column, $operator);
            } else {
                $value = $this->mapValue($column, $value);
            }
        }

        return parent::orWhere($this->mapWhereColumn($column), $operator, $value);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $values = is_iterable($values)
            ? collect($values)->map(fn ($value) => $this->mapValue($column, $value))->all()
            : $values;

        return parent::whereIn($this->mapColumn($column), $values, $boolean, $not);
    }

    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return parent::whereNotIn($this->mapColumn($column), $values, $boolean);
    }

    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        return parent::whereNull($this->mapColumnList((array) $columns), $boolean, $not);
    }

    public function whereNotNull($columns, $boolean = 'and')
    {
        return parent::whereNotNull($this->mapColumnList((array) $columns), $boolean);
    }

    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->mapColumn($column), $direction);
    }

    public function orderByDesc($column)
    {
        return parent::orderByDesc($this->mapColumn($column));
    }

    public function groupBy(...$groups)
    {
        return parent::groupBy(...$this->mapColumnList($groups));
    }

    public function pluck($column, $key = null)
    {
        return parent::pluck($this->mapColumn($column), $key ? $this->mapColumn($key) : null);
    }

    public function max($column)
    {
        return parent::max($this->mapColumn($column));
    }

    public function min($column)
    {
        return parent::min($this->mapColumn($column));
    }

    public function sum($column)
    {
        return parent::sum($this->mapColumn($column));
    }

    public function avg($column)
    {
        return parent::avg($this->mapColumn($column));
    }

    public function update(array $values)
    {
        return parent::update($this->mapValueKeys($values));
    }

    protected function mapColumns(array $columns): array
    {
        $mapped = [];

        foreach ($columns as $key => $column) {
            if ($column instanceof Expression) {
                $mapped[$key] = $column;
                continue;
            }

            if (is_string($column) && $this->isVirtualColumn($column)) {
                continue;
            }

            $mapped[$key] = $this->mapColumn($column);
        }

        return $mapped ?: [$this->model->getQualifiedKeyName()];
    }

    protected function mapWhereColumn($column)
    {
        if (is_array($column)) {
            if (!array_is_list($column)) {
                $mapped = [];
                foreach ($column as $key => $value) {
                    $mapped[$this->mapColumn($key)] = $value;
                }

                return $mapped;
            }

            return collect($column)
                ->map(fn ($condition) => is_array($condition) && isset($condition[0])
                    ? array_replace($condition, [0 => $this->mapColumn($condition[0])])
                    : $condition)
                ->all();
        }

        return $this->mapColumn($column);
    }

    protected function mapColumnList(array $columns): array
    {
        return array_map(fn ($column) => $this->mapColumn($column), $columns);
    }

    protected function mapValueKeys(array $values): array
    {
        $mapped = [];

        foreach ($values as $key => $value) {
            if (!$this->isVirtualColumn($key)) {
                $mapped[$this->mapColumn($key)] = $this->mapValue($key, $value);
            }
        }

        return $mapped;
    }

    protected function mapColumn($column)
    {
        if (!$column || $column instanceof Expression || !is_string($column)) {
            return $column;
        }

        if (str_contains($column, ' as ') || str_contains($column, '(') || $column === '*') {
            return $column;
        }

        $prefix = null;
        $name = $column;

        if (str_contains($column, '.')) {
            [$prefix, $name] = explode('.', $column, 2);
            if ($prefix !== $this->model->getTable()) {
                return $column;
            }
        }

        $aliases = method_exists($this->model, 'getLegacyAliases') ? $this->model->getLegacyAliases() : [];
        $mapped = $aliases[$name] ?? $name;

        return $prefix ? "{$prefix}.{$mapped}" : $mapped;
    }

    protected function isVirtualColumn(string $column): bool
    {
        $name = str_contains($column, '.') ? explode('.', $column, 2)[1] : $column;
        $virtuals = method_exists($this->model, 'getLegacyVirtualColumns') ? $this->model->getLegacyVirtualColumns() : [];

        return in_array($name, $virtuals, true);
    }

    protected function mapValue(string $column, mixed $value): mixed
    {
        return method_exists($this->model, 'mapLegacyValue')
            ? $this->model->mapLegacyValue($column, $value)
            : $value;
    }
}
