<?php

namespace App\Exceptions;

use Exception;

class MerchantAmountLimitException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct(
            'Subscriptions are temporarily disabled for your account.',
            1,
        );
    }
}
