<?php

namespace App\Exceptions;

use Exception;

class PasswordAlreadyUsedException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(trans('passwords.used'), 8);
    }
}
