<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderedProduct;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubscriptionFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "subscription:fix
        {--sundays : Fix Sunday schedules}
        {--meta-info : Fix meta info}
        {--overflow : Fix subscriptions with orders having overflowing dates}
        {--shipping-fee= : Remove Shipping fee}
        {--shipping-method-id= : Shipping method id}
        {--product-id= : Product id}
        {--shopify-products : Fix info on Shopify products}
        {--merchant=* : Include only subscriptions from the given merchants}
        {--id=* : Include only subscriptions with the given IDs}
        {--dry-run : Don't actually update the database}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix problems with subscriptions';

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
        if ($this->option('meta-info')) {
            $this->fixMetaInfo();
        }

        if ($this->option('sundays')) {
            $this->fixInvalidSundays();
        }

        if ($this->option('overflow')) {
            $this->fixOverflowingOrders();
        }

        if ($this->option('shopify-products')) {
            $this->fixShopifyProductInfo();
        }

        if ($this->option('shipping-fee')) {
            $this->fixShippingFee();
        }

        return 0;
    }

    /**
     * Fix overflowed billing dates of orders.
     *
     * @return void
     */
    protected function fixOverflowingOrders()
    {
        $subscriptions = Subscription::query()
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->whereHas('orders', function (Builder $query) {
                $query
                    ->whereNotNull('payment_schedule')
                    ->where('payment_schedule->frequency', 'monthly')
                    ->where('payment_schedule->day', '>', 28)
                    ->where('payment_schedule->day', '<>', DB::raw('DAY(`billing_date`)'))
                    ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::FAILED, OrderStatus::INCOMPLETE]);
            })
            ->with(['orders' => function ($query) {
                $query
                    ->whereNotNull('payment_schedule')
                    ->where('payment_schedule->frequency', 'monthly')
                    ->where('payment_schedule->day', '>', 28)
                    ->where('payment_schedule->day', '<>', DB::raw('DAY(`billing_date`)'))
                    ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::FAILED, OrderStatus::INCOMPLETE]);
            }])
            ->get()
            ->each(function (Subscription $subscription) {
                // $subscription->orders->each(function (Order $order) {
                //     $order->billing_date = Carbon::parse($order->billing_date)
                //         ->subMonth()
                //         ->setUnitNoOverflow('day', $order->payment_schedule['day'], 'month')
                //         ->toDateString();

                //     $order->saveQuietly();
                // });
            });

        $orderIds = $subscriptions->pluck('orders')->flatten()->pluck('id')->unique();

        $this->info($orderIds->join(', '));
    }

    /**
     * Fix subscriptions scheduled on Sundays.
     *
     * @return void
     */
    protected function fixInvalidSundays()
    {
        $subscriptions = Subscription::query()
            ->where('payment_schedule->day_of_week', 7)
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->getOutput()
                ->success('No subscriptions with invalid day of week found.');
        }

        $message = "Subscriptions with Invalid Day of Week: {$subscriptions->count()}";

        $this->getOutput()->warning($message);

        if (!$this->confirm('Do you want to fix the schedules?')) {
            return;
        }

        DB::beginTransaction();

        try {
            $subscriptions->each(function (Subscription $subscription) {
                $subscription->updateQuietly([
                    'payment_schedule' => array_merge($subscription->payment_schedule, [
                        'day_of_week' => 0,
                    ]),
                ]);
            });

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();

                $this->getOutput()->success('Subscriptions schedules fixed successfully.');
            }
        } catch (Throwable $e) {
            DB::rollBack();
        }
    }

    /**
     * Fix subscriptions with stringified meta info.
     *
     * @return void
     */
    protected function fixMetaInfo()
    {
        Subscription::query()
            ->whereNotNull('other_info')
            ->cursor()
            ->tapEach(function (Subscription $subscription) {
                if (is_string($subscription->other_info)) {
                    $subscription->other_info = json_decode($subscription->other_info, true);
                }

                $subscription->saveQuietly();
            })
            ->all();
    }


    /**
     * Remove shipping fee based on products and shipping method
     *
     * @return void
     */
    protected function fixShippingFee()
    {
        // TODO: Remove after runnning in prod.
        $subscriptions = Subscription::query()
            ->where('shipping_method_id', $this->option('shipping-method-id'))
            ->whereHas('products', function ($query) {
                $query->where('product_id', $this->option('product-id'));
            }, '=', 1)
            ->get();

        $this->table(
            ['Subscription ID', 'Affected Products', 'Affected Orders'],
            $subscriptions
                ->map(function (Subscription $subscription) {
                    return [
                        $subscription->getKey(),
                        $subscription->products->count(),
                        $subscription->orders->count(),
                    ];
                })
                ->toArray()
        );

        if (!$this->confirm('Do you want to remove shipping fee?')) {
            return;
        }

        DB::beginTransaction();

        try {
            $subscriptions->each(function (Subscription $subscription) {
                $subscription->forceFill(['custom_shipping_fee' => 0])->update();
            });

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();

                $this->getOutput()->success('Shipping fee removed successfully.');
            }
        } catch (Throwable $e) {
            $this->getOutput()->error($e->getMessage());

            DB::rollBack();
        }
    }

    /**
     * Fix info of Shopify products.
     *
     * @return void
     */
    protected function fixShopifyProductInfo()
    {
        $subscriptions = Subscription::query()
            ->with([
                'products' => function ($query) {
                    $query
                        ->whereNotNull('shopify_product_info->id')
                        ->whereNull('shopify_product_info->admin_graphql_api_id')
                        ->whereNull('shopify_product_info->variant_id');
                },
                'orders' => function ($query) {
                    $query
                        ->satisfied(false)
                        ->with(['products' => function ($query) {
                            $query
                                ->whereNotNull('shopify_product_info->id')
                                ->whereNull('shopify_product_info->admin_graphql_api_id')
                                ->whereNull('shopify_product_info->variant_id');
                        }]);
                },
            ])
            ->whereNull('cancelled_at')
            ->whereNull('completed_at')
            ->whereHas('merchant', function ($query) {
                $query->whereNotNull('shopify_info');
            })
            ->where(function ($query) {
                $query
                    ->whereHas('products', function ($query) {
                        $query
                            ->whereNotNull('shopify_product_info->id')
                            ->whereNull('shopify_product_info->admin_graphql_api_id')
                            ->whereNull('shopify_product_info->variant_id');
                    })
                    ->orWhereHas('orders', function ($query) {
                        $query
                            ->satisfied(false)
                            ->whereHas('products', function ($query) {
                                $query
                                    ->whereNotNull('shopify_product_info->id')
                                    ->whereNull('shopify_product_info->admin_graphql_api_id')
                                    ->whereNull('shopify_product_info->variant_id');
                            });
                    });
            })
            ->when($this->option('merchant'), function ($query, $merchantIds) {
                $query->whereIn('merchant_id', $merchantIds);
            })
            ->when($this->option('id'), function ($query, $ids) {
                $query->whereKey($ids);
            })
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->getOutput()
                ->success('No subscriptions with missing Shopify product variant IDs.');
        }

        $message = "Subscriptions with Missing Shopify Product Variant IDs: {$subscriptions->count()}";

        $this->getOutput()->warning($message);

        $this->table(
            ['Subscription ID', 'Affected Products', 'Affected Orders'],
            $subscriptions
                ->map(function (Subscription $subscription) {
                    return [
                        $subscription->getKey(),
                        $subscription->products->count(),
                        $subscription->orders->count(),
                    ];
                })
                ->toArray()
        );

        if (!$this->confirm('Do you want to fix the products?')) {
            return;
        }

        DB::beginTransaction();

        try {
            $subscriptions->each(function (Subscription $subscription) {
                $subscription->products->each(function (SubscribedProduct $product) {
                    $id = data_get($product, 'shopify_product_info.id');

                    if (
                        is_numeric($id)
                        && !data_get($product, 'shopify_product_info.variant_id')
                    ) {
                        $product->shopify_product_info = array_merge($product->shopify_product_info, [
                            'variant_id' => $id,
                        ]);

                        $product->saveQuietly();
                    }
                });

                $subscription->orders->each(function (Order $order) {
                    $order->products->each(function (OrderedProduct $product) {
                        $id = data_get($product, 'shopify_product_info.id');

                        if (
                            is_numeric($id)
                            && !data_get($product, 'shopify_product_info.variant_id')
                        ) {
                            $product->shopify_product_info = array_merge($product->shopify_product_info, [
                                'variant_id' => $id,
                            ]);

                            $product->saveQuietly();
                        }
                    });
                });
            });

            if ($this->option('dry-run')) {
                DB::rollBack();
            } else {
                DB::commit();

                $this->getOutput()->success('Shopify product variant IDs set successfully.');
            }
        } catch (Throwable $e) {
            $this->getOutput()->error($e->getMessage());

            DB::rollBack();
        }
    }
}
