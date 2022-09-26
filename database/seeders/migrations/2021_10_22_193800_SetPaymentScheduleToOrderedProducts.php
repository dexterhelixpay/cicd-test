<?php

use App\Models\OrderedProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetPaymentScheduleToOrderedProducts_2021_10_22_193800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            OrderedProduct::query()
                ->whereNull('payment_schedule')
                ->whereHas('subscribedProduct', function ($query) {
                    $query->whereNotNull('payment_schedule');
                })
                ->cursor()
                ->tapEach(function (OrderedProduct $product) {
                    $subscribeProduct = $product->subscribedProduct()->first();

                    if ($schedule = $subscribeProduct->payment_schedule) {
                        ksort($schedule);

                        $product->payment_schedule = $schedule;
                        $product->saveQuietly();
                    }
                })
                ->all();
        });
    }
}
