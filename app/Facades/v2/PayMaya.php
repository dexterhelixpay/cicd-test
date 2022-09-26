<?php

namespace App\Facades\v2;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Libraries\PayMaya\v2\CustomerCard customerCards(string $version = 'v1')
 * @method static \App\Libraries\PayMaya\v2\Customer customers(string $version = 'v1')
 * @method static \App\Libraries\PayMaya\v2\Payment payments(string $version = 'v1')
 * @method static \App\Libraries\PayMaya\v2\Wallet wallets(string $version = 'v2')
 */
class PayMaya extends Facade
{
    /**
     * Constant representing a missing card error.
     *
     * @var string
     */
    const ERROR_CARD_NOT_FOUND = 'PY0027';

    /**
     * Constant representing a missing customer error.
     *
     * @var string
     */
    const ERROR_CUSTOMER_NOT_FOUND = 'PY0023';

    /**
     * Constant representing a payment requiring authentication.
     *
     * @var string
     */
    const STATUS_FOR_AUTHENTICATION = 'FOR_AUTHENTICATION';

    /**
     * Constant representing a payment expired status.
     *
     * @var string
     */
    const STATUS_PAYMENT_EXPIRED = 'PAYMENT_EXPIRED';

    /**
     * Constant representing a payment failed status.
     *
     * @var string
     */
    const STATUS_PAYMENT_FAILED = 'PAYMENT_FAILED';

    /**
     * Constant representing a payment success status.
     *
     * @var string
     */
    const STATUS_PAYMENT_SUCCESS = 'PAYMENT_SUCCESS';

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'paymaya';
    }
}
