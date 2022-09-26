<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class MigrateCustomerCards_2021_10_25_134700 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $orderQuery = function ($query) {
            $query
                ->where('orders.payment_type_id', PaymentType::CARD)
                ->where('order_status_id', OrderStatus::PAID)
                ->whereNotNull('orders.paymaya_card_token_id');
        };

        Customer::query()
            ->whereHas('orders', $orderQuery)
            ->cursor()
            ->tapEach(function (Customer $customer) use ($orderQuery) {
                $customer->load(['orders' => $orderQuery]);

                $customer->orders
                    ->unique('paymaya_card_token_id')
                    ->each(function (Order $order) use ($customer) {
                        if (!$fundSource = data_get($order, 'payment_info.payment.fundSource')) {
                            return;
                        }

                        $customer->cards()
                            ->firstOrNew([
                                'card_token_id' => data_get($fundSource, 'id'),
                            ], [
                                'card_type' => data_get($fundSource, 'details.scheme'),
                                'masked_pan' => data_get($fundSource, 'details.last4'),
                            ])
                            ->verify()
                            ->touch();
                    });
            })
            ->all();
    }
}
