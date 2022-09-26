<?php

namespace App\Exceptions;

use Exception;

class MultipleSessionException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(
            'Multiple Session Detected.',
            6,
        );
    }
}
