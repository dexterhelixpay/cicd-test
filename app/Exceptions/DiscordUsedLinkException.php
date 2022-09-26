<?php

namespace App\Exceptions;

use Exception;

class DiscordUsedLinkException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(
            'You already used this Discord link!',
            10
        );
    }
}
