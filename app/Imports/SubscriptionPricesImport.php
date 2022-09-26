<?php

namespace App\Imports;

use App\Models\Subscription;
use App\Models\Order;
use App\Models\SubscribedProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SubscriptionPricesImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    protected $merchant;

    /**
     * The subscriptions collection.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $subscriptions;

    /**
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function __construct($merchant)
    {
        $this->merchant = $merchant;
    }

    /**
     * @param  \Illuminate\Support\Collection  $collection
     * @return \Illuminate\Support\Collection
     */
    public function collection(Collection $collection)
    {
        $subscriptionIds = $collection->pluck('subscription_id')
            ->filter()
            ->unique()
            ->values();

        $subscribedProductIds = $collection->pluck('subscribed_product_id')
            ->filter()
            ->unique()
            ->values();

        $productIds = $collection->pluck('product_id')
            ->filter()
            ->unique()
            ->values();

        $subscriptions = Subscription::query()
            ->with([
                'products' => function ($query) use ($subscribedProductIds, $productIds) {
                    $query->whereKey($subscribedProductIds)->orWhereIn('product_id', $productIds);
                },
                'orders' => function ($query) use ($subscribedProductIds, $productIds) {
                    $query
                        ->with(['products' => function ($query) use ($subscribedProductIds, $productIds) {
                            $query
                                ->whereIn('subscribed_product_id', $subscribedProductIds)
                                ->orWhereIn('product_id', $productIds);
                        }])
                        ->satisfied(false);
                },
            ])
            ->whereKey($subscriptionIds)
            ->whereNull('cancelled_at')
            ->get();

        $this->subscriptions = $collection
            ->groupBy('subscription_id')
            ->map(function (Collection $rows, $subscriptionId) use ($subscriptions) {
                /** @var \App\Models\Subscription */
                if (!$subscription = $subscriptions->find($subscriptionId)) {
                    return null;
                }

                $rows->each(function (Collection $row) use ($subscription) {
                    $subscribedProductId = $row->get('subscribed_product_id');

                    if (!$product = $subscription->products->find($subscribedProductId)) {
                        return;
                    }

                    $product
                        ->fill([
                            'price' => (float) $row->get('price'),
                            'quantity' => $product->are_multiple_orders_allowed
                                ? (int) $row->get('quantity')
                                : min(1, (int) $row->get('quantity'))
                        ])
                        ->setTotalPrice()
                        ->update();

                    $subscription->setTotalPrice();

                    $subscription->orders
                        ->each(function (Order $order) use ($row, $subscribedProductId) {
                            $product = $order->products
                                ->firstWhere('subscribed_product_id', $subscribedProductId);

                            if (!$product) return;

                            $product
                                ->fill([
                                    'price' => (float) $row->get('price'),
                                    'quantity' => $product->are_multiple_orders_allowed
                                        ? (int) $row->get('quantity')
                                        : min(1, (int) $row->get('quantity'))
                                ])
                                ->setTotalPrice()
                                ->update();

                            $order->setTotalPrice();
                        });
                });

                return $subscription;
            })
            ->filter()
            ->values();
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'subscription_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $exists = Subscription::query()
                        ->whereKey($value)
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'subscribed_product_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $exists = SubscribedProduct::query()
                        ->whereKey($value)
                        ->whereRelation('subscription', 'merchant_id', $this->merchant->getKey())
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'quantity' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Get the imported
     *
     * @return \Illuminate\Support\Collection|null
     */
    public function getSubscriptions()
    {
        return $this->subscriptions;
    }
}
