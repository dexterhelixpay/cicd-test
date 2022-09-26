<?php

use App\Models\Order;
use App\Models\OrderedProduct;
use App\Models\SubscribedProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SortPaymentSchedule_2021_10_04_160600 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (Schema::hasColumn((new Order)->getTable(), 'payment_schedule')) {
            DB::transaction(function () {
                Order::query()
                    ->whereNotNull('payment_schedule')
                    ->cursor()
                    ->tapEach(function (Order $order) {
                        if ($schedule = $order->payment_schedule) {
                            ksort($schedule);

                            $order->payment_schedule = $schedule;
                            $order->saveQuietly();
                        }
                    })
                    ->all();
            });
        }

        if (Schema::hasColumn((new SubscribedProduct)->getTable(), 'payment_schedule')) {
            DB::transaction(function () {
                SubscribedProduct::query()
                    ->whereNotNull('payment_schedule')
                    ->cursor()
                    ->tapEach(function (SubscribedProduct $product) {
                        if ($schedule = $product->payment_schedule) {
                            ksort($schedule);

                            $product->payment_schedule = $schedule;
                            $product->saveQuietly();
                        }
                    })
                    ->all();
            });
        }

        if (Schema::hasColumn((new OrderedProduct)->getTable(), 'payment_schedule')) {
            DB::transaction(function () {
                OrderedProduct::query()
                    ->whereNotNull('payment_schedule')
                    ->cursor()
                    ->tapEach(function (OrderedProduct $product) {
                        if ($schedule = $product->payment_schedule) {
                            ksort($schedule);

                            $product->payment_schedule = $schedule;
                            $product->saveQuietly();
                        }
                    })
                    ->all();
            });
        }
    }
}
