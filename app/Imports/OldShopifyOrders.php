<?php

namespace App\Imports;

use App\Imports\ShopifyOrders\Orders;
use App\Libraries\Shopify;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OldShopifyOrders implements WithMultipleSheets
{
    use Importable;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The Shopify library.
     *
     * @var \App\Libraries\Shopify
     */
    public $shopify;

    /**
     * Create a new import instance.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function __construct($merchant)
    {
        $this->merchant = $merchant->load('shippingMethods');
        $this->shopify = new Shopify(
            $merchant->shopify_domain,
            $merchant->shopify_info['access_token']
        );
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new Orders($this->merchant),
        ];
    }
}
