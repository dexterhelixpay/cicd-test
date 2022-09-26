<?php

use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreateSavedSearchMerchants_2022_04_04_111600 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNotNull('shopify_info')
                ->cursor()
                ->tapEach(function (Merchant $merchant)  {
                    $shopify = new Shopify(
                        $merchant->shopify_domain, $merchant->shopify_info['access_token']
                    );

                    $shopify->createSegment('Helixpay', "customer_tags = 'HelixPay'");
                })
                ->all();
        });
    }
}
