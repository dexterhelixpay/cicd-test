<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetRecurrenceInCurrentOrders_2021_09_21_070100 extends Seeder
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
                ->cursor()
                ->tapEach(function(Order $order) {
                    $subscription = $order->subscription;

                    if (!$subscription) return;

                    $order->forceFill(['payment_schedule' => $subscription->payment_schedule])->saveQuietly();
                })
                ->all();
        });
    }
}
