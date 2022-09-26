<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GuzzleHttp\Client checkout()
 * @method static \GuzzleHttp\Client transfer()
 */
class Brankas extends Facade
{
    const TRANSFER_IN_PROGRESS = 'IN_PROGRESS';
    const TRANSFER_INVOICE_CREATED = 'INVOICE_CREATED';
    const TRANSFER_AWAITING_LOGIN = 'AWAITING_LOGIN';
    const TRANSFER_AWAITING_LOGIN_TFA = 'AWAITING_LOGIN_TFA';
    const TRANSFER_LOGIN_ERROR = 'LOGIN_ERROR';
    const TRANSFER_AWAITING_ACCOUNT_SELECT = 'AWAITING_ACCOUNT_SELECT';
    const TRANSFER_AWAITING_TRANSFER_TFA = 'AWAITING_TRANSFER_TFA';
    const TRANSFER_COMPLETED = 'COMPLETED';
    const TRANSFER_ERROR = 'ERROR';
    const TRANSFER_EXPIRED = 'EXPIRED';
    const TRANSFER_SUCCESS = 'SUCCESS';

    const TRANSACTION_COMPLETED = 2;
    const TRANSACTION_ERROR = 3;
    const TRANSACTION_LOGIN_ERROR = 4;
    const TRANSACTION_INVOICE_CREATED = 5;
    const TRANSACTION_AWAITING_LOGIN = 6;
    const TRANSACTION_AWAITING_LOGIN_TFA = 7;
    const TRANSACTION_EXPIRED = 11;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'brankas';
    }
}
