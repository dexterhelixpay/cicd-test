<?php

use App\Models\Order;
use App\Models\PaymentType;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetOrderIsAutoChargeFlagFalseForDigitalAndBank_2022_07_07_134800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Order::query()->whereNotIn('payment_type_id', [
                PaymentType::CARD, PaymentType::PAYMAYA_WALLET,
            ])->update(['is_auto_charge' => false]);

            Subscription::query()->whereNotIn('payment_type_id', [
                PaymentType::CARD, PaymentType::PAYMAYA_WALLET,
            ])->update(['is_auto_charge' => false]);
        });
    }
}
