<?php

namespace App\Libraries\JsonApi\Filters;

use App\Support\Model;
use Illuminate\Support\Arr;

class Filter
{
    /**
     * The data type of the value.
     *
     * @var string|null
     * @see https://www.php.net/manual/en/function.settype.php
     */
    protected $type = null;

    /**
     * The SQL clause to be used.
     *
     * @var string
     */
    protected $clause = 'where';

    /**
     * The column to be filtered.
     *
     * @var string
     */
    protected $column;

    /**
     * Create a new filter instance.
     *
     * @param  string  $column
     */
    public function __construct(string $column)
    {
        $this->column = $column;
    }

    /**
     * Get the query clauses.
     *
     * @param  mixed  $value
     * @param  \Illuminate\Database\Eloquent\Builder|null  $builder
     * @return array
     */
    public function __invoke($value, $builder = null): array
    {
        if ($builder) {
            $this->column = $builder->qualifyColumn($this->column);
        }

        return array_merge_recursive(
            $this->equalTo($value),
            $this->notEqualTo($value),
            $this->notNull($value),
            $this->null($value)
        );
    }

    /**
     * Create a new filter instance based on the given model column.
     *
     * @param  mixed  $model
     * @param  string  $column
     * @return self
     */
    public static function for($model, string $column)
    {
        if (!Model::hasColumn($model, $column)) {
            return new Missing($column);
        }

        switch (Model::getColumnType($model, $column)) {
            case 'boolean':
                return new Boolean($column);

            case 'date':
                return new Date($column);

            case 'datetime':
                return new Timestamp($column);

            case 'float':
            case 'double':
                return new Decimal($column);

            case 'integer':
            case 'bigint':
                return new Integer($column);

            case 'json':
                return new Json($column);

            case 'string':
            case 'text':
                return new Text($column);

            case 'time':
                return new Time($column);

            default:
                return new static($column);
        }
    }

    /**
     * @param  mixed  $value
     * @param  bool  $not
     * @return array
     */
    protected function between($value, bool $not = false)
    {
        $value = $this->getFilterValue($value, $not ? 'between' : 'not_between');

        if (is_null($value) || (is_string($value) && !preg_match('/(?<!\\\),/', $value))) {
            return [];
        }

        $values = array_slice(preg_split('/(?<!\\\),/', $value), 0, 2);
        $values = array_map(function ($value) {
            return $this->castValue($value);
        }, $values);

        if (count($values) < 2) {
            return $this->greaterThanOrEqualTo($values[0]);
        }

        return ['whereBetween' => [[$this->column, $values]]];
    }

    /**
     * @param  mixed  $value
     * @param  bool  $not
     * @param  string|null  $clause
     * @return array
     */
    protected function equalTo($value, bool $not = false, ?string $clause = null)
    {
        $keys = $not ? ['ne', 'not_equals'] : ['eq', 'equals'];

        if (
            (is_string($value) && preg_match('/(?<!\\\),/', $value))
            || is_null($value = $this->getFilterValue($value, $keys, !$not))
        ) {
            return [];
        }

        if ($not) {
            return ['whereRaw' => [
                ["not {$this->column} <=> ?", $this->castValue($value)],
            ]];
        }

        return [$clause ?? $this->clause => [
            [$this->column, $this->castValue($value)],
        ]];
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $clause
     * @return array
     */
    protected function greaterThan($value, ?string $clause = null)
    {
        $value = $this->getFilterValue($value, 'gt');

        return is_null($value)
            ? []
            : [$clause ?? $this->clause => [[$this->column, '>', $this->castValue($value)]]];
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $clause
     * @return array
     */
    protected function greaterThanOrEqualTo($value, ?string $clause = null)
    {
        $value = $this->getFilterValue($value, 'gte');

        return is_null($value)
            ? []
            : [$clause ?? $this->clause => [[$this->column, '>=', $this->castValue($value)]]];
    }

    /**
     * @param  mixed  $value
     * @param  boolean  $not
     * @return array
     */
    protected function in($value, $not = false)
    {
        $clause = $not ? 'whereNotIn' : 'whereIn';
        $value = $this->getFilterValue($value, $not ? 'not_in' : 'in', !$not);

        if (is_null($value)) {
            return [];
        }

        $values = preg_split('/(?<!\\\),/', data_get($value, $not ? 'not_in' : 'in', $value));
        $values = array_map(function ($value) {
            return $this->castValue(stripslashes($value));
        }, $values);

        return [$clause => [[$this->column, $values]]];
    }

    /**
     * @param  mixed  $value
     * @param  string  $clause
     * @return array
     */
    protected function lessThan($value, $clause = 'where')
    {
        $value = $this->getFilterValue($value, 'lt');

        return is_null($value)
            ? []
            : [$clause ?? $this->clause => [[$this->column, '<', $this->castValue($value)]]];
    }

    /**
     * @param  mixed  $value
     * @param  string  $clause
     * @return array
     */
    protected function length($value, $clause = 'whereJsonLength')
    {
        $comparisonOperators = ['gt', 'gte', 'lt', 'lte', 'is_null', 'is_not_null'];

        if (
            is_null($value)
            || in_array(array_keys($value)[0], $comparisonOperators)
        ) {
            return [];
        }

        return [$clause => [[$this->column, $this->castValue($value)]]];
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function lessThanOrEqualTo($value)
    {
        $value = $this->getFilterValue($value, 'lte');

        return is_null($value)
            ? []
            : [$clause ?? $this->clause => [[$this->column, '<=', $this->castValue($value)]]];
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function notBetween($value)
    {
        return $this->between($value, true);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $clause
     * @return array
     */
    protected function notEqualTo($value, $clause = null)
    {
        return $this->equalTo($value, true, $clause);
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function notIn($value)
    {
        return $this->in($value, true);
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function notNull($value)
    {
        $value = $this->getFilterValue($value, ['not_null', 'is_not_null', 'isnotnull']);

        return is_null($value)
            ? []
            : ['whereNotNull' => [[$this->column]]];
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function null($value)
    {
        $value = $this->getFilterValue($value, ['null', 'is_null', 'isnull']);

        return is_null($value)
            ? []
            : ['whereNull' => [[$this->column]]];
    }

    /**
     * Cast the value to the given type.
     *
     * @param  mixed  $value
     * @param  string|null  $type
     * @return mixed
     */
    protected function castValue($value, $type = null)
    {
        if (!($type ?? $this->type) || is_null($value)) {
            return $value;
        }

        settype($value, $this->type);

        return $value;
    }

    /**
     * @param  array  $filters
     * @return array
     */
    protected function cleanFilters($filters)
    {
        if (!Arr::has($filters, [$this->clause, 'whereIn'])) {
            return $filters;
        }

        $filters['whereIn'] = collect($filters['whereIn'])
            ->reject(function ($whereIn) use ($filters) {
                return collect($filters[$this->clause])
                    ->contains(function ($filter) use ($whereIn) {
                        return count($filter) === 3
                            && $filter[0] === $whereIn[0]
                            && $filter[1] === '='
                            && [$filter[2]] === $whereIn[1];
                    });
            })
            ->toArray();

        return $filters;
    }

    /**
     * Get the value from the given filter and keys.
     *
     * @param  mixed  $filter
     * @param  mixed  $keys
     * @param  bool  $isArrayable
     * @return mixed
     */
    protected function getFilterValue($filter, $keys = [], $isArrayable = false)
    {
        $keys = Arr::wrap($keys);

        if (is_array($filter)) {
            return Arr::hasAny($filter, $keys)
                ? Arr::first($filter, function ($value, $key) use ($keys) {
                    return in_array($key, $keys);
                })
                : null;
        }

        if (!$isArrayable && count($keys)) {
            return null;
        }

        return $filter;
    }
}
