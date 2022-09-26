<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Console\Command;

class OrderCancel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:cancel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel subscription/orders once billing date has lapsed.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Order::query()
            ->forCancellation()
            ->cursor()
            ->tapEach(function (Order $order) {
                $subscription = $order->subscription()->first();

                $hasPaidSkippedOrders = $subscription->orders()
                    ->whereIn('order_status_id', [OrderStatus::PAID, OrderStatus::SKIPPED])
                    ->exists();

                if (!$hasPaidSkippedOrders) {
                    $order->order_status_id = OrderStatus::CANCELLED;
                    $order->update();
                }
            })
            ->all();
    }
}
