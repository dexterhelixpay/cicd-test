<?php

namespace App\Libraries\JsonApi\Filters;

class Integer extends Filter
{
    /**
     * The data type of the value.
     *
     * @var string|null
     * @see https://www.php.net/manual/en/function.settype.php
     */
    protected $type = 'integer';

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
            $this->greaterThan($value),
            $this->greaterThanOrEqualTo($value),
            $this->in($value),
            $this->lessThan($value),
            $this->lessThanOrEqualTo($value),
            $this->notIn($value)
        );

        return $this->cleanFilters($filters);
    }
}
