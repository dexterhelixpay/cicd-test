<?php

namespace App\Libraries\JsonApi\Filters;

use Illuminate\Support\Carbon;

class Timestamp extends Filter
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
            $this->between($value),
            $this->greaterThan($value),
            $this->greaterThanOrEqualTo($value),
            $this->in($value),
            $this->notBetween($value),
            $this->notIn($value),
            $this->lessThan($value),
            $this->lessThanOrEqualTo($value)
        );

        return $this->cleanFilters($filters);
    }

    /**
     * Cast the value to the given type.
     *
     * @param  mixed  $value
     * @param  string}null  $type
     * @return mixed
     */
    protected function castValue($value, $type = null)
    {
        return is_null($value)
            ? $value
            : Carbon::parse($value)->toDateTimeString();
    }
}
