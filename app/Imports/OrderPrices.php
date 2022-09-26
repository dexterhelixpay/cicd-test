<?php

namespace App\Imports;

use App\Models\Order;
use App\Models\OrderedProduct;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OrderPrices implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    protected $merchant;

    /**
     * The orders collection.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $orders;

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
        $this->orders = $collection
            ->groupBy('order_id')
            ->map(function (Collection $rows, $orderId) {
                $order = Order::find($orderId);

                $rows->each(function (Collection $row) use ($order) {
                    $product = $order->products()->find($row->get('ordered_product_id'));

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

                return $order->fresh('products');
            });
    }

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $exists = Order::query()
                        ->whereKey($value)
                        ->whereRelation('subscription', 'merchant_id', $this->merchant->getKey())
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'ordered_product_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $exists = OrderedProduct::query()
                        ->whereKey($value)
                        ->whereHas('order.subscription', function ($query) {
                            $query->where('merchant_id', $this->merchant->getKey());
                        })
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
    public function getOrders()
    {
        return $this->orders;
    }
}
