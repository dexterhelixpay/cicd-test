<?php

namespace App\Auth\Passwords\Code;

use Illuminate\Auth\Passwords\PasswordBroker as Broker;

class PasswordBroker extends Broker
{
    /**
     * Constant representing an invalid token.
     *
     * @var string
     */
    const INVALID_TOKEN = 'passwords.code';
}
