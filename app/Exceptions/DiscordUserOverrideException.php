<?php

namespace App\Exceptions;

use Exception;

class DiscordUserOverrideException extends Exception
{

    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(
            'You have failed to override user! You are joining the same discord user!',
            11
        );
    }
}
