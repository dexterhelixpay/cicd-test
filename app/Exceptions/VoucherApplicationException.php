<?php

namespace App\Exceptions;

use Exception;

class VoucherApplicationException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message
     * @param  int  $code
     * @return void
     */
    public function __construct($message, $code)
    {
        parent::__construct($message, $code);
    }
}
