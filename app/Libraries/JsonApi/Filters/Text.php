<?php

namespace App\Libraries\JsonApi\Filters;

use Illuminate\Support\Str;

class Text extends Filter
{
    /**
     * Get the query clauses.
     *
     * @param  mixed  $value
     * @param  \Illuminate\Database\Eloquent\Builder|null  $builder
     * @return array
     */
    public function __invoke($value, $builder = null): array
    {
        $defaults = parent::__invoke($value, $builder);

        $filters = array_merge_recursive(
            $defaults,
            $this->endsWith($value),
            $this->in($value),
            $this->like($value),
            $this->startsWith($value)
        );

        return $this->cleanFilters($filters);
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function endsWith($value)
    {
        return $this->like($value, '%%%s');
    }

    /**
     * @param  mixed  $value
     * @param  string  $format
     * @return array
     */
    protected function like($value, string $format = '%%%s%%')
    {
        if (Str::startsWith($format, '%%') && Str::endsWith($format, '%%')) {
            $keys = ['like'];
        } elseif (Str::startsWith($format, '%%')) {
            $keys = ['ends', 'ends_with'];
        } elseif (Str::endsWith($format, '%%')) {
            $keys = ['starts', 'starts_with'];
        } else {
            return [];
        }

        $value = $this->getFilterValue($value, $keys);
        if (is_null($value)) return [];

        return ['where' => [[$this->column, 'like', $this->castValue(sprintf($format, $value))]]];
    }

    /**
     * @param  mixed  $value
     * @return array
     */
    protected function startsWith($value)
    {
        return $this->like($value, '%s%%');
    }
}
