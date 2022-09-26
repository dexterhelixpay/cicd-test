<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use App\Support\PaymentSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DummyOrderGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dummy-order:generate
        {--orderCount= : no of orders}
        {--subscriptionId= : Subscription ID}
        {--orderId= : Order ID to be replicated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->option('subscriptionId')) {
            return $this->getOutput()->error('Subscription ID is required!');
        }

        if (!$this->option('orderId')) {
            return $this->getOutput()->error('Order ID is required!');
        }

        $subscription = Subscription::find($this->option('subscriptionId'));

        if (!$subscription) {
            return $this->getOutput()->error('Subscription not found!');
        }

        $order = Order::find($this->option('orderId'));

        if (!$order) {
            return $this->getOutput()->error('Order not found!');
        }

        for ($i=0; $i < $this->option('orderCount') ?? 500; $i++) {
            DB::transaction(function () use ($order, $subscription) {
                $orderReference = $order;

                $nextBillingDate = now()->toDateTimeString();

                $newOrder = $subscription->orders()
                    ->make(Arr::except($order->toArray(), [
                        'voucher_id',
                        'voucher_code',
                        'order_status_id',
                        'payment_status_id',
                        'payment_info',
                        'shipping_date',
                        'fulfillment_date',
                        'paid_at',
                        'payment_attempted_at',
                        'has_payment_lapsed',
                        'previous_balance',
                        'shopify_order_id',
                    ]))
                    ->fill([
                        'billing_date' => $nextBillingDate,
                        'payment_schedule' => $orderReference->payment_schedule,
                        'payment_type_id' => isset($paymentType)
                            ? $paymentType
                            : $orderReference->payment_type_id,
                        'bank_id' => isset($paymentType)
                            ? $order->bank_id
                            : $orderReference->bank_id,
                    ])
                    ->setAttribute('group_number', $orderReference->group_number);

                $newOrder->save();

                 $subscription->products()
                    ->where('group_number', $orderReference->group_number)
                    ->get()
                    ->each(function (SubscribedProduct $product) use ($newOrder, $order, $subscription) {
                        ($orderedProduct = $newOrder->products()->make($product->toArray()))
                            ->subscribedProduct()
                            ->associate($product);

                        if ($maxOrderCount = $product->max_discounted_order_count) {
                            $orderCount = $subscription->orders()
                                ->whereHas('products', function ($query) use ($product) {
                                    $query
                                        ->where('subscribed_product_id', $product->getKey())
                                        ->where('is_discounted', true);
                                })
                                ->count();

                            if ($orderCount < $maxOrderCount) {
                                $orderedProduct->forceFill([
                                    'price' => $product->discounted_price,
                                    'is_discounted' => true,
                                ]);
                            }
                        }

                        $subscription->cascadeVoucher($newOrder);

                        $orderedProduct->setTotalPrice()->save();
                    });

                $newOrder->setTotalPrice();
            });
        }

        return 0;
    }
}
