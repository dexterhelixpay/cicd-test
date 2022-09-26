<?php

namespace App\Libraries\JsonApi\Filters;

use Illuminate\Support\Carbon;

class Date extends Timestamp
{
    /**
     * The SQL clause to be used.
     *
     * @var string
     */
    protected $clause = 'whereDate';

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
            : Carbon::parse($value)->toDateString();
    }
}
