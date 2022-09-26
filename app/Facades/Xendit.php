<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Libraries\Xendit\Account accounts(string $version = 'v2')
 * @method static \App\Libraries\Xendit\CallbackUrl callbackUrls()
 * @method static \App\Libraries\Xendit\FeeRule feeRules()
 * @method static \App\Libraries\Xendit\EWalletCharge eWalletCharges()
 */
class Xendit extends Facade
{
    /**
     * Constant representing Indian Rupee.
     *
     * @var string
     */
    const CURRENCY_IDR = 'IDR';

    /**
     * Constant representing Philippine Peso.
     *
     * @var string
     */
    const CURRENCY_PHP = 'PHP';

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'xendit';
    }
}
