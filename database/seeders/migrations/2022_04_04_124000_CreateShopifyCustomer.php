<?php

use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreateShopifyCustomer_2022_04_04_124000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Customer::query()
                ->whereNotNull('name')
                ->whereHas('merchant', function ($query) {
                    $query->whereNotNull('shopify_info');
                })
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Customer $customer)  {
                    $merchant = $customer->merchant;

                    $shopify = new Shopify(
                        $merchant->shopify_domain, $merchant->shopify_info['access_token']
                    );

                    if (!$customer->shopify_id) {
                        $response = $shopify->createCustomer($customer);

                        $customer->forceFill([
                            'shopify_id' => $response->json('data.customerCreate.customer.legacyResourceId')
                        ])->saveQuietly();
                    }

                    $customer->fresh();

                    if (!$customer->shopify_id) return;

                    $shopify->addTag(
                        "gid://shopify/Customer/{$customer->shopify_id}",
                        "HelixPay"
                    );
                })
                ->all();
        });
    }
}
