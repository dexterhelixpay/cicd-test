<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GuzzleHttp\Client checkout(bool $secret = false)
 * @method static array getKeys(string $type)
 * @method static \GuzzleHttp\Client payBy(bool $secret = false)
 * @method static \GuzzleHttp\Client payments(bool $secret = false)
 * @method static \App\Libraries\PayMaya\PayMaya setPwpKeys(string $publicKey, string $secretKey)
 * @method static \App\Libraries\PayMaya\PayMaya setVaultKeys(string $publicKey, string $secretKey)
 * @method static mixed withPwpKeys(string $publicKey, string $secretKey, \Closure $callback)
 * @method static mixed withVaultKeys(string $publicKey, string $secretKey, \Closure $callback)
 */
class PayMaya extends Facade
{
    /**
     * The events for PayMaya webhooks.
     *
     * @var array
     */
    const EVENTS = [
        '3DS_PAYMENT_DROPOUT',
        '3DS_PAYMENT_FAILURE',
        '3DS_PAYMENT_SUCCESS',
        'PAYMENT_EXPIRED',
        'PAYMENT_FAILED',
        'PAYMENT_SUCCESS',
        'RECURRING_PAYMENT_FAILURE',
        'RECURRING_PAYMENT_SUCCESS',
    ];

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'paymaya.old';
    }
}
