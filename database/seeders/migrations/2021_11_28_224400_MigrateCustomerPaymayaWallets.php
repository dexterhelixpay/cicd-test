<?php

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateCustomerPaymayaWallets_2021_11_28_224400 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $subscriptionQuery = function ($query) {
                $query->whereNotNull('subscriptions.paymaya_link_id')
                    ->whereNotNull('subscriptions.paymaya_wallet_mobile_number')
                    ->whereNotNull('subscriptions.paymaya_wallet_customer_name');
            };

            Customer::query()
                ->whereHas('subscriptions', $subscriptionQuery)
                ->cursor()
                ->tapEach(function (Customer $customer) use ($subscriptionQuery) {
                    $customer->load(['subscriptions' => $subscriptionQuery]);

                    $customer->subscriptions
                        ->unique('paymaya_wallet_mobile_number')
                        ->each(function (Subscription $subscription) use ($customer) {

                            $customer->wallets()
                                ->firstOrNew([
                                    'mobile_number' => $subscription->paymaya_wallet_mobile_number,
                                ], [
                                    'name' => $subscription->paymaya_wallet_customer_name,
                                    'link_id' => $subscription->paymaya_link_id,
                                ])
                                ->verify()
                                ->touch();
                        });
                })
                ->all();
        });
    }
}
