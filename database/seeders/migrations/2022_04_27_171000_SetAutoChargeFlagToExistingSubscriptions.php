<?php

use App\Models\Order;
use App\Models\PaymentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetAutoChargeFlagToExistingSubscriptions_2022_04_27_171000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Order::query()
                ->whereIn('payment_type_id', [PaymentType::CARD, PaymentType::PAYMAYA_WALLET])
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Order $order)  {
                    $order->forceFill(['is_auto_charge' => true])->update();
                    if ($order->subscription) {
                        $order->subscription->forceFill(['is_auto_charge' => true])->update();
                    }
                })
                ->all();
        });
    }
}
