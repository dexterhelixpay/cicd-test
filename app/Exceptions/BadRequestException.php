<?php

namespace App\Exceptions;

use Exception;

class BadRequestException extends Exception
{
    /**
     * Get the exception status code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return 400;
    }
}
