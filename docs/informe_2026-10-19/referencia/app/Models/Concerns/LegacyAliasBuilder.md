# LegacyAliasBuilder

Fuente: [app/Models/Concerns/LegacyAliasBuilder.php](../../../../../../app/Models/Concerns/LegacyAliasBuilder.php). Corte: 19 de octubre de 2026.


## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

class LegacyAliasBuilder extends Builder
{


























}
```

## select

Visibilidad: `public`. Líneas originales 10–13.

```php
    public function select($columns = ['*'])
    {
        return parent::select($this->mapColumns(is_array($columns) ? $columns : func_get_args()));
    }
```

## addSelect

Visibilidad: `public`. Líneas originales 15–20.

```php
    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();

        return parent::addSelect($this->mapColumns($columns));
    }
```

## get

Visibilidad: `public`. Líneas originales 22–25.

```php
    public function get($columns = ['*'])
    {
        return parent::get($this->mapColumns((array) $columns));
    }
```

## paginate

Visibilidad: `public`. Líneas originales 27–30.

```php
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return parent::paginate($perPage, $this->mapColumns((array) $columns), $pageName, $page, $total);
    }
```

## simplePaginate

Visibilidad: `public`. Líneas originales 32–35.

```php
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return parent::simplePaginate($perPage, $this->mapColumns((array) $columns), $pageName, $page);
    }
```

## where

Visibilidad: `public`. Líneas originales 37–48.

```php
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
```

## orWhere

Visibilidad: `public`. Líneas originales 50–61.

```php
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
```

## whereIn

Visibilidad: `public`. Líneas originales 63–70.

```php
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $values = is_iterable($values)
            ? collect($values)->map(fn ($value) => $this->mapValue($column, $value))->all()
            : $values;

        return parent::whereIn($this->mapColumn($column), $values, $boolean, $not);
    }
```

## whereNotIn

Visibilidad: `public`. Líneas originales 72–75.

```php
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return parent::whereNotIn($this->mapColumn($column), $values, $boolean);
    }
```

## whereNull

Visibilidad: `public`. Líneas originales 77–80.

```php
    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        return parent::whereNull($this->mapColumnList((array) $columns), $boolean, $not);
    }
```

## whereNotNull

Visibilidad: `public`. Líneas originales 82–85.

```php
    public function whereNotNull($columns, $boolean = 'and')
    {
        return parent::whereNotNull($this->mapColumnList((array) $columns), $boolean);
    }
```

## orderBy

Visibilidad: `public`. Líneas originales 87–90.

```php
    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->mapColumn($column), $direction);
    }
```

## orderByDesc

Visibilidad: `public`. Líneas originales 92–95.

```php
    public function orderByDesc($column)
    {
        return parent::orderByDesc($this->mapColumn($column));
    }
```

## groupBy

Visibilidad: `public`. Líneas originales 97–100.

```php
    public function groupBy(...$groups)
    {
        return parent::groupBy(...$this->mapColumnList($groups));
    }
```

## pluck

Visibilidad: `public`. Líneas originales 102–105.

```php
    public function pluck($column, $key = null)
    {
        return parent::pluck($this->mapColumn($column), $key ? $this->mapColumn($key) : null);
    }
```

## max

Visibilidad: `public`. Líneas originales 107–110.

```php
    public function max($column)
    {
        return parent::max($this->mapColumn($column));
    }
```

## min

Visibilidad: `public`. Líneas originales 112–115.

```php
    public function min($column)
    {
        return parent::min($this->mapColumn($column));
    }
```

## sum

Visibilidad: `public`. Líneas originales 117–120.

```php
    public function sum($column)
    {
        return parent::sum($this->mapColumn($column));
    }
```

## avg

Visibilidad: `public`. Líneas originales 122–125.

```php
    public function avg($column)
    {
        return parent::avg($this->mapColumn($column));
    }
```

## update

Visibilidad: `public`. Líneas originales 127–130.

```php
    public function update(array $values)
    {
        return parent::update($this->mapValueKeys($values));
    }
```

## mapColumns

Visibilidad: `protected`. Líneas originales 132–150.

```php
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
```

## mapWhereColumn

Visibilidad: `protected`. Líneas originales 152–172.

```php
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
```

## mapColumnList

Visibilidad: `protected`. Líneas originales 174–177.

```php
    protected function mapColumnList(array $columns): array
    {
        return array_map(fn ($column) => $this->mapColumn($column), $columns);
    }
```

## mapValueKeys

Visibilidad: `protected`. Líneas originales 179–190.

```php
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
```

## mapColumn

Visibilidad: `protected`. Líneas originales 192–229.

```php
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

        if ($this->model instanceof \App\Models\User && in_array($name, ['semestre', 'grupo'], true)
            && \Illuminate\Support\Facades\Schema::hasTable('matriculas_academicas')) {
            $career = (int) app(\App\Support\CareerContext::class)->careerId();
            $field = $name === 'semestre' ? 'semestre' : 'clave_grupo';
            return new Expression("(SELECT g.{$field} FROM matriculas_academicas m JOIN grupos_academicos g ON g.id=m.grupo_id JOIN carrera_periodos cp ON cp.carrera_id=m.carrera_id AND cp.periodo_id=m.periodo_id WHERE m.estudiante_id=usuarios.id AND m.carrera_id={$career} AND m.estado='activa' AND cp.estado IN ('abierto','en_cierre') LIMIT 1)");
        }

        if ($this->model instanceof \App\Models\RepositoryDocument && $name === 'document_category' && \Illuminate\Support\Facades\Schema::hasColumn('documentos', 'categoria_detalle')) return new Expression('COALESCE(documentos.categoria_detalle, documentos.categoria)');

        if ($this->model instanceof \App\Models\Project && $name === 'semestre' && \Illuminate\Support\Facades\Schema::hasTable('proyecto_periodos')) {
            return new Expression("(SELECT g.semestre FROM grupos_academicos g WHERE g.id = COALESCE((SELECT pp.grupo_id FROM proyecto_periodos pp JOIN carrera_periodos cp ON cp.carrera_id=pp.carrera_id AND cp.periodo_id=pp.periodo_id WHERE pp.proyecto_id=proyectos.id AND pp.carrera_id=proyectos.carrera_id AND cp.estado IN ('abierto','en_cierre') LIMIT 1), proyectos.grupo_id) LIMIT 1)");
        }

        $aliases = method_exists($this->model, 'getLegacyAliases') ? $this->model->getLegacyAliases() : [];
        $mapped = $aliases[$name] ?? $name;

        return $prefix ? "{$prefix}.{$mapped}" : $mapped;
    }
```

## isVirtualColumn

Visibilidad: `protected`. Líneas originales 231–237.

```php
    protected function isVirtualColumn(string $column): bool
    {
        $name = str_contains($column, '.') ? explode('.', $column, 2)[1] : $column;
        $virtuals = method_exists($this->model, 'getLegacyVirtualColumns') ? $this->model->getLegacyVirtualColumns() : [];

        return in_array($name, $virtuals, true);
    }
```

## mapValue

Visibilidad: `protected`. Líneas originales 239–244.

```php
    protected function mapValue(string $column, mixed $value): mixed
    {
        return method_exists($this->model, 'mapLegacyValue')
            ? $this->model->mapLegacyValue($column, $value)
            : $value;
    }
```
