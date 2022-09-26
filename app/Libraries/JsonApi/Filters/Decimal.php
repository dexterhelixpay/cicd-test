<?php

namespace App\Libraries\JsonApi\Filters;

class Decimal extends Integer
{
    /**
     * The data type of the value.
     *
     * @var string|null
     * @see https://www.php.net/manual/en/function.settype.php
     */
    protected $type = 'float';
}
