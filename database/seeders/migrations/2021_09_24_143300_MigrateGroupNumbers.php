<?php

use App\Models\Order;
use App\Models\OrderedProduct;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateGroupNumbers_2021_09_24_143300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Subscription::query()
                ->withTrashed()
                ->cursor()
                ->tapEach(function (Subscription $subscription) {
                    $this->applyGroupNumbers($subscription);
                    $this->migratePaymentSchedules($subscription);
                    $this->migrateSubscribedProductKeys($subscription);
                })
                ->all();
        });
    }

    /**
     * Apply group numbers to current subscribed products and orders.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function applyGroupNumbers($subscription)
    {
        if ($subscription->products()->whereNotNull('group_number')->doesntExist()) {
            $subscription->products()->update(['group_number' => 1]);
        }

        if ($subscription->orders()->whereNotNull('group_number')->doesntExist()) {
            $subscription->orders()->update(['group_number' => 1]);
        }
    }

    /**
     * Migrate payment schedules to subscribed/ordered products.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function migratePaymentSchedules($subscription)
    {
        if (!$subscription->payment_schedule) {
            return;
        }

        $subscription->products()
            ->where(function ($query) {
                $query
                    ->whereNull('payment_schedule')
                    ->orWhere('payment_schedule', 'like', 'null');
            })
            ->get()
            ->each(function (SubscribedProduct $product) use ($subscription) {
                $product->updateQuietly(['payment_schedule' => $subscription->payment_schedule]);
            });

        $subscription->orders()
            ->where(function ($query) {
                $query
                    ->whereNull('payment_schedule')
                    ->orWhere('payment_schedule', 'like', 'null');
            })
            ->get()
            ->each(function (Order $order) use ($subscription) {
                $order->updateQuietly(['payment_schedule' => $subscription->payment_schedule]);
            });
    }

    /**
     * Migrate the product keys to  ordered products.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function migrateSubscribedProductKeys($subscription)
    {
        $subscribedProducts = $subscription->products()->get();

        $subscription->orders()->with('products')->get()
            ->each(function (Order $order) use ($subscribedProducts) {
                $order->products->whereNull('subscribed_product_id')
                    ->each(function (OrderedProduct $orderedProduct) use ($subscribedProducts) {
                        if ($orderedProduct->product_id) {
                            $foundProduct = $subscribedProducts
                                ->where('product_id', $orderedProduct->product_id)
                                ->first();
                        } else {
                            $foundProduct = $subscribedProducts
                                ->where('title', $orderedProduct->title)
                                ->first();
                        }

                        if ($foundProduct) {
                            $orderedProduct->subscribedProduct()
                                ->associate($foundProduct)
                                ->saveQuietly();
                        }
                    });
            });
    }
}
