<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\Client\PendingRequest client(string $prefix = null)
 * @method static int getMerchantId()
 * @method static string generateSecureHash(string $reference, string $currency, float $amount, string $paymentType)
 * @method static bool verifyDatafeed(array $data, string $secureHash)
 */
class PesoPay extends Facade
{
    /**
     * Constant representing the PH currency.
     *
     * @var string
     */
    const CURRENCY_PHP = '608';

    /**
     * Constant representing the English language.
     *
     * @var string
     */
    const LANG_ENGLISH = 'E';

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'pesopay';
    }
}
