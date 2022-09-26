<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Libraries\Shopify\Customer customers(string $shopUrl, string $accessToken)
 * @method static \App\Libraries\Shopify\Order orders(string $shopUrl, string $accessToken)
 * @method static \App\Libraries\Shopify\Product products(string $shopUrl, string $accessToken)
 * @method static \App\Libraries\Shopify\ProductVariant productVariants(string $shopUrl, string $accessToken)
 */
class Shopify extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'shopify';
    }
}
