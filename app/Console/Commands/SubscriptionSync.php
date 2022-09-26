<?php

namespace App\Console\Commands;

use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "subscription:sync
        {id : The subscription ID}
        {--address : Sync the customer's address}
        {--product=* : The product IDs}";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Sync info to the given subscription";

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
        if (!$subscription = Subscription::find($this->argument('id'))) {
            return $this->getOutput()->error("The selected subscription doesn't exist.");
        }

        if (!$merchant = $subscription->merchant()->first()) {
            return $this->getOutput()->error("The subscription's merchant doesn't exist.");
        }


        $this->syncAddress($subscription);
        $this->syncProducts($subscription, $merchant);

        return Command::SUCCESS;
    }

    /**
     * Sync the customer's address to the given subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function syncAddress($subscription)
    {
        if (!$this->option('address')) {
            return;
        }

        if (!$customer = $subscription->customer()->first()) {
            return $this->getOutput()->error("The customer doesn't exist.");
        }

        $data = [
            'payor' => $customer->name,
            'billing_address' => $customer->address,
            'billing_province' => $customer->province,
            'billing_city' => $customer->city,
            'billing_barangay' => $customer->barangay,
            'billing_zip_code' => $customer->zip_code,

            'recipient' => $customer->name,
            'shipping_address' => $customer->address,
            'shipping_province' => $customer->province,
            'shipping_city' => $customer->city,
            'shipping_barangay' => $customer->barangay,
            'shipping_zip_code' => $customer->zip_code,
        ];

        DB::transaction(function () use ($subscription, $data) {
            $subscription->update($data);

            $subscription->orders()
                ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::FAILED, OrderStatus::INCOMPLETE])
                ->get()
                ->each
                ->update($data);
        });
    }

    /**
     * Sync the products to the given subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    protected function syncProducts($subscription, $merchant)
    {
        if (!count($this->option('product'))) {
            return;
        }

        $now = now();
        $frequencies = ['weekly', 'semimonthly', 'monthly', 'quarterly', 'annually'];

        $productDetails = collect($this->option('product'))
            ->mapWithKeys(function ($product) use ($now, $frequencies) {
                $parts = explode(',', $product);
                $parts[1] = $parts[1] ?? 1;

                if (!in_array($parts[2] ?? null, $frequencies)) {
                    $parts[2] = 'monthly';
                }

                $parts[3] = $parts[3] ?? null;

                switch ($parts[2]) {
                    case 'weekly':
                    case 'semimonthly':
                        if (
                            is_null($parts[3])
                            || !in_array((int) $parts[3], range(0, 6))
                        ) {
                            $parts[3] = $now->dayOfWeek;
                        }

                        $schedule = ['day_of_week' => $parts[3]];
                        break;

                    case 'monthly':
                    case 'quarterly':
                        if (
                            is_null($parts[3])
                            || !in_array((int) $parts[3], range(1, 31))
                        ) {
                            $parts[3] = $now->day;
                        }

                        $schedule = ['day' => $parts[3]];
                        break;

                    default:
                        //
                }

                return [$parts[0] => [
                    'quantity' => $parts[1],
                    'payment_schedule' => array_merge(['frequency' => $parts[2]], $schedule ?? []),
                ]];
            });

        $products = $merchant->products()->whereKey($productDetails->keys())->get();

        if ($products->isEmpty()) {
            return $this->getOutput()->error('At least one product is required.');
        }

        $this->table(
            ['Product ID', 'Product Title', 'Quantity', 'Frequency'],
            $products->map(function (Product $product) use ($productDetails) {
                $details = $productDetails->get($product->getKey());

                return [
                    $product->getKey(),
                    $product->title,
                    $details['quantity'],
                    json_encode($details['payment_schedule']),
                ];
            })->toArray()
        );

        if (!$this->confirm('This will sync the above products to the subscription. Continue?')) {
            return;
        }

        DB::transaction(function () use ($subscription, $products, $productDetails) {
            $subscription->syncSubscribedProducts(
                $subscription->mapProductData(
                    $products
                        ->map(function (Product $product) use ($productDetails) {
                            $details = $productDetails->get($product->getKey());
                            $productVariant = $product->variants()
                                ->whereHas('optionValues', function ($query) use ($details) {
                                    $query
                                        ->where('value', $details['payment_schedule']['frequency'])
                                        ->whereHas('option', function ($query) {
                                            $query->where('code', 'recurrence');
                                        });
                                })
                                ->first();

                            $attributes = array_merge($product->only([
                                'title',
                                'description',
                                'price',
                                'are_multiple_orders_allowed',
                                'is_shippable',
                            ]), [
                                'product_id' => $product->getKey(),
                                'product_variant_id' => optional($productVariant)->getKey(),
                                'shopify_product_info' => $product->shopify_info,
                            ], $details);

                            return compact('attributes');
                        })
                        ->toArray()
                )
            );
        });
    }
}
