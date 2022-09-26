<?php

namespace App\Libraries\JsonApi\Filters;

use Illuminate\Support\Carbon;

class Time extends Timestamp
{
    /**
     * The SQL clause to be used.
     *
     * @var string
     */
    protected $clause = 'whereTime';

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
            : Carbon::parse($value)->toTimeString();
    }
}
