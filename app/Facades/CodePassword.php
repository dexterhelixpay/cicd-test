<?php

namespace App\Facades;

use App\Auth\Passwords\Code\PasswordBroker;
use Illuminate\Support\Facades\Password;

class CodePassword extends Password
{
    /**
     * Constant representing an invalid token.
     *
     * @var string
     */
    const INVALID_TOKEN = PasswordBroker::INVALID_TOKEN;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'auth.password.code';
    }
}
