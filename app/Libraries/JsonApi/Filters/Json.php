<?php

namespace App\Libraries\JsonApi\Filters;

class Json extends Filter
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

        return array_merge_recursive(
            $defaults,
            $this->greaterThan($value, 'whereJsonLength'),
            $this->greaterThanOrEqualTo($value, 'whereJsonLength'),
            $this->lessThan($value, 'whereJsonLength'),
            $this->lessThanOrEqualTo($value, 'whereJsonLength'),
            $this->length($value)
        );
    }
}
