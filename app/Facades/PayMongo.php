<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GuzzleHttp\Client client()
 */
class PayMongo extends Facade
{
    /**
     * The events for PayMaya webhooks.
     *
     * @var array
     */
    const EVENTS = [
        'source.chargeable',
        'payment.paid',
        'payment.failed',
    ];

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'paymongo';
    }
}
