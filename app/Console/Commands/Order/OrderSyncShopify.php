<?php

namespace App\Console\Commands\Order;

use App\Jobs\CreateShopifyOrder;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderSyncShopify extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:sync-shopify
        {--id=* : The order IDs to sync}
        {--force : Overwrite existing Shopify orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync recently paid orders to Shopify';

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
        $orders = Order::query()
            ->where('order_status_id', OrderStatus::PAID)
            ->whereHas('subscription.merchant', function ($query) {
                $query->whereNotNull('shopify_domain')->whereNotNull('shopify_info');
            })
            ->when(count($this->option('id')), function ($query) {
                $query->whereKey($this->option('id'));
            }, function ($query) {
                $query->where('paid_at', '>=', now()->subDay()->startOfDay()->toDateTimeString());
            })
            ->when(!$this->option('force'), function ($query) {
                $query->whereNull('shopify_order_id');
            })
            ->get()
            ->map(function (Order $order) {
                try {
                    (new CreateShopifyOrder($order))->handle();
                } catch (Throwable $e) {
                    Log::error($e->getMessage(), [
                        'order' => $order->getKey(),
                        'file' => __FILE__,
                        'trace' => $e->getTrace(),
                    ]);
                }

                return $order->refresh();
            });

        $this->table(
            ['Order ID', 'Shopify Order ID'],
            $orders->map(function (Order $order) {
                return [$order->getKey(), $order->shopify_order_id ?? 'N/A'];
            })->toArray()
        );

        return 0;
    }
}
