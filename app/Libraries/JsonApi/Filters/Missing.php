<?php

namespace App\Libraries\JsonApi\Filters;

class Missing extends Filter
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
        return [];
    }
}
