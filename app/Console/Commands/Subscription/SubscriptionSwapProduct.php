<?php

namespace App\Console\Commands\Subscription;

use App\Models\Merchant;
use App\Models\Order;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubscriptionSwapProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:swap-product
        {merchant : The merchant whose subscriptions will be updated}
        {old : The old product to be swapped}
        {new : The new product to be swapped}
        {--match-old-title= : Match the old product with the title}
        {--id=* : Swap the product of specific subscriptions}
        {--except=* : Ignore the specific subscriptions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Swap a product from the merchant's subscriptions";

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // php artisan subscription:swap-product 274 4503 8402 --match-old-title="Create your own bundle:"

        if (!$merchant = Merchant::find($this->argument('merchant'))) {
            return $this->getOutput()->error('Merchant not found.');
        }

        /** @var \App\Models\Product */
        $newProduct = $merchant->products()
            ->with('allVariants.optionValues.option')
            ->find($this->argument('new'));

        if (!$newProduct) {
            return $this->getOutput()->error('New product not found.');
        }

        $oldProduct = $merchant->products()->find($this->argument('old'));
        $oldTitle = $this->option('match-old-title');

        $productQuery = function ($query) use ($oldTitle) {
            return $query
                ->where('product_id', $this->argument('old'))
                ->when($oldTitle, function ($query, $title) {
                    $query->where('title', 'like', "%{$title}%");
                });
        };

        $this->table(['', 'Product'], [
            [
                'From',
                $this->argument('old') . ' - ' . ($oldTitle ?? $oldProduct?->title ?? 'N/A'),
            ],
            [
                'To',
                $this->argument('new') . ' - ' . $newProduct->title,
            ],
        ]);

        $subscriptions = $merchant->subscriptions()
            ->with([
                'customer',
                'orders' => function ($query) use ($productQuery) {
                    $query->satisfied(false)->withWhereHas('products', $productQuery);
                },
            ])
            ->withWhereHas('products', $productQuery)
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->whereHas('products', $productQuery)
            ->when($this->option('id'), function ($query, $ids) {
                $query->whereKey($ids);
            })
            ->when($this->option('except'), function ($query, $ids) {
                $query->whereKeyNot($ids);
            })
            ->get();

        if ($subscriptions->isEmpty()) {
            return $this->getOutput()->error('No subscriptions detected with the old product.');
        }

        $this->newLine();
        $this->table(
            ['ID', 'Customer', 'Product', 'Quantity'],
            $subscriptions->flatMap(function (Subscription $subscription) {
                return $subscription->products
                    ->map(function (SubscribedProduct $product) use ($subscription) {
                        return [
                            $subscription->getKey(),
                            $subscription->customer->name,
                            $product->title,
                            $product->quantity,
                        ];
                    });
            })
        );

        if (!$this->confirm($subscriptions->count() . ' found. Continue swapping?')) {
            return;
        }

        $subscriptions->each(function (Subscription $subscription) use ($newProduct) {
            DB::transaction(function () use ($subscription, $newProduct) {
                $subscription->products
                    ->each(function (SubscribedProduct $product) use ($subscription, $newProduct) {
                        $this->table(['Key', 'Value'], [
                            ['Subscription', $subscription->getKey()],
                            ['Sub Product ID', $product->getKey()],
                            ['Product', $product->product_id . ' - ' . $product->title],
                            ['Frequency', $frequency = data_get($product, 'payment_schedule.frequency')],
                            ['Option Values', json_encode($product->option_values)],
                        ]);

                        $choices = $newProduct->allVariants
                            ->filter(function (ProductVariant $variant) use ($frequency) {
                                return $variant->optionValues
                                    ->contains(function (ProductOptionValue $value) use ($frequency) {
                                        return $value->value === $frequency
                                            && $value->option->code === 'recurrence';
                                    });
                            })
                            ->values()
                            ->map(function (ProductVariant $variant) {
                                return $variant->getKey() . ' - ' . json_encode($variant->mapOptionValues());
                            })
                            ->toArray();

                        $variant = $newProduct->allVariants
                            ->find((int) $this->choice('Select variant', $choices));

                        $product->forceFill([
                            'title' => $newProduct->title,
                            'description' => $newProduct->description,
                            'option_values' => $variant->mapOptionValues(),
                            'price' => $variant->price,
                        ]);

                        if ($variant->shopify_variant_id) {
                            $product->shopify_product_info = [
                                'id' => $variant->shopify_variant_id,
                                'variant_id' => $variant->shopify_variant_id,
                            ];
                        }

                        $product
                            ->product()
                            ->associate($newProduct)
                            ->variant()
                            ->associate($variant)
                            ->setTotalPrice()
                            ->save();

                        $subscription->setTotalPrice();

                        $subscription->orders->each(function (Order $order) use ($product) {
                            $order->products
                                ->firstWhere('subscribed_product_id', $product->getKey())
                                ->forceFill($product->only([
                                    'title',
                                    'description',
                                    'option_values',
                                    'price',
                                    'shopify_product_info',
                                ]))
                                ->product()
                                ->associate($product->product_id)
                                ->variant()
                                ->associate($product->product_variant_id)
                                ->setTotalPrice()
                                ->save();

                            $order->setTotalPrice();
                        });

                        $this->getOutput()->success(
                            "Subscription #{$subscription->getKey()}"
                            . " and {$subscription->orders->count()} unpaid order/s"
                            . ' successfully updated.'
                        );
                    });
            });
        });

        return 0;
    }
}
