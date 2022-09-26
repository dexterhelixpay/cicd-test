<?php

use App\Models\Order;
use App\Models\PaymentStatus;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetSoldCountToAllOrders_2022_07_27 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ini_set("memory_limit", "-1");

        Order::query()
            ->with(['subscription' => ['merchant', 'initialOrder']])
            ->has('products')
            ->has('subscription.customer')
            ->has('subscription.merchant')
            ->whereNotNull('payment_schedule')
            ->where('payment_status_id', PaymentStatus::PAID)
            ->cursor()
            ->tapEach(function (Order $order) {
                if ($order->subscription?->initialOrder?->id != $order->id) return;

                DB::transaction(function () use ($order) {
                    $service = new ProductService;

                    $service->incrementSales(
                        $order->subscription->merchant,
                        $order->subscription->products()->get()->toArray()
                    );
                });
            })
            ->all();
    }
}
