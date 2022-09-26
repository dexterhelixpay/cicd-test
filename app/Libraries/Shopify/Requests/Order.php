<?php

namespace App\Libraries\Shopify\Requests;

use App\Facades\Shopify;
use App\Models\ConvenienceType;
use App\Models\Merchant;
use App\Models\Order as OrderModel;
use App\Models\OrderedProduct;
use App\Models\PaymentType;
use App\Models\Voucher;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Order
{
    /**
     * The request data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Get the request data.
     *
     * @return array
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Generate request data from the given order.
     *
     * @param  \App\Models\Order  $order
     * @return $this
     */
    public function from(OrderModel $order)
    {
        $merchant = $order->subscription->merchant;

        $lineItems = $order->products()
            ->where('quantity', '>', 0)
            ->get()
            ->map(function(OrderedProduct $product) use ($merchant) {
                $item = $product->only('quantity');
                $variant = $product->variant()->first();

                if ($product->shopify_custom_links) {
                    $item['properties'] = collect($product->shopify_custom_links)
                        ->mapWithKeys(function ($file) {
                            if (!$path = data_get($file, 'path') ?? data_get($file, 'image_path')) {
                                return [Str::random() => null];
                            }

                            $fileName = Str::of($path)->explode('/')->last();

                            return [$fileName => $path];
                        })
                        ->filter()
                        ->toArray();
                }

                $variantId = data_get($variant, 'shopify_variant_id')
                    ?? data_get($product, 'shopify_product_info.variant_id');

                if ($variantId) {
                    return array_merge(
                        $item,
                        $product->only('price', 'title'),
                        $merchant->only('name'),
                        [
                            'variant_id' => $variantId,
                            'is_out_of_stock' => $this->isOutOfStock($merchant, $variantId),
                        ]
                    );
                }

                return array_merge(
                    $item, $product->only('price', 'title'), $merchant->only('name')
                );
            })
            ->values();

        $customer = $order->subscription->customer;
        $payorName = Str::splitName($order->payor, false);

        $billingAddress = collect([
            'name' => $order->payor,
            'first_name' => $payorName['firstName'],
            'last_name' => $payorName['lastName'],
            'phone' => $customer->country
                ? $customer->formatted_mobile_number
                : $customer->mobile_number,
            'address1' => $order->billing_address,
            'address2' => $order->billing_barangay,
            'city' => $order->billing_city,
            'province' => $order->billing_province,
            'country' => $order->billing_country ?? 'Philippines',
            'zip' => $order->billing_zip_code,
        ])->filter()->toArray();

        $hasOutOfStock = $lineItems->contains(function ($item) {
            return data_get($item, 'is_out_of_stock', false);
        });

        $orderData = [
            'billing_address' => $billingAddress,
            'line_items' => $lineItems->map(function ($item) {
                return Arr::except($item, 'is_out_of_stock');
            })->toArray(),
            'inventory_behaviour' => $hasOutOfStock ? 'bypass' : 'decrement_obeying_policy',
            'note' => $order->subscription->delivery_note,
            'financial_status' => 'paid',
            'tags' => 'HelixPay',
        ];

        if ($order->total_price) {
            $orderData['transactions'] = [[
                'kind' => 'capture',
                'status' => 'success',
                'currency' => 'PHP',
                'gateway' =>  'manual',
                'amount' => $order->previous_balance
                    ? ($order->total_price - $order->previous_balance)
                    : ($order->total_price ?? 0)
            ]];
        }

        if ($customer->shopify_id) {
            $orderData['customer'] = ['id' => $customer->shopify_id];
        } else {
            $nameParts = Str::splitName($customer->name, false);

            $orderData['customer'] = [
                'first_name' => $nameParts['firstName'],
                'last_name' => $nameParts['lastName'],
                'email' => Str::validEmail($customer->email) ?: null,
            ];
        }

        if ($order->recipient) {
            $recipientName = Str::splitName($order->recipient, false);

            $orderData['shipping_address'] = collect([
                'name' => $order->recipient,
                'first_name' => $recipientName['firstName'],
                'last_name' => $recipientName['lastName'],
                'phone' => $customer->country
                    ? $customer->formatted_mobile_number
                    : $customer->mobile_number,
                'address1' => $order->shipping_address,
                'address2' => $order->shipping_barangay,
                'city' => $order->shipping_city,
                'province' => $order->shipping_province,
                'country' => $order->shipping_country ?? 'Philippines',
                'zip' => $order->shipping_zip_code,
            ])->filter()->toArray();
        }

        if (
            $order->shippingMethod
            || !$order->total_price
            || $order->custom_shipping_fee
        ) {
            $shippingFee = $order->total_price ? ($order->shipping_fee ?? 0) : 0;

            $orderData = array_merge($orderData, [
                'shipping_lines' => [
                    [
                        'title' => $order->custom_shipping_fee
                            ? 'Custom Shipping Fee'
                            : (optional($order->shippingMethod)->name ?? 'Shipping Fee'),
                        'price' => $order->custom_shipping_fee ?? $shippingFee,
                        'price_set' => $shippingFee,
                    ]
                ]
            ]);
        }

        $convenienceFee = $merchant->convenience_fee;
        $taxLines = [];

        if (
            $convenienceFee && $order->total_price > 0
            || $order->convenience_fee
        ) {
            $rate = 0;

            if ($merchant->convenience_type_id == ConvenienceType::PERCENTAGE) {
                $convenienceFee = $order->total_price * ($convenienceFee / 100);
                $rate = $convenienceFee / 100;
            }

            array_push($taxLines, [
                'title' => 'Convenience Fee',
                'price' =>  $order->convenience_fee ?? $convenienceFee,
                'rate' => $rate
            ]);
        }

        if ($order->vat_amount) {
            array_push($taxLines, [
                'title' => 'VAT',
                'price' => $order->vat_amount
            ]);
        }

        if (count($taxLines)) {
            $orderData = array_merge($orderData, [
                'tax_lines' => $taxLines
            ]);
        }

        $discountAmount = 0;
        $discountCodes = [];

        if ($voucher = $order->voucher()->first()) {
            $subtotal = $this->computeSubtotal($lineItems);
            $shippingFee = $order->total_price ? ($order->shipping_fee ?? 0) : 0;

            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $subtotal + $shippingFee,
                'products' => $order->products()->get(),
                'customer' => $customer,
                'order' => $this
            ]);

            $amount = round(data_get($discount, 'discount_amount'), 2);
            $discountAmount += $amount;

            array_push($discountCodes, $voucher->code);
        }

        if (
            $order->payment_type_id == PaymentType::CARD
            && ($cardDiscount = $merchant->card_discount)
        ) {
            $subtotal = $this->computeSubtotal($lineItems);
            $shippingFee = $order->total_price ? ($order->shipping_fee ?? 0) : 0;

            $discountAmount += round(($subtotal + $shippingFee) * ($cardDiscount / 100), 2);

            array_push($discountCodes, "Card Discount {$cardDiscount}%");
        }

        if (count($discountCodes)) {
            $orderData = array_merge($orderData, [
                'discount_codes' => [
                    [
                        'code' => collect($discountCodes)->join(', ', ' and '),
                        'amount' => $discountAmount,
                        'type' => 'fixed_amount',
                    ],
                ],
            ]);
        }

        $this->data = ['order' => $orderData];

        return $this;
    }


    /**
     * Compute subtotal
     *
     * @param  array||object $lineItems
     * @return float
     */
    protected function computeSubtotal($lineItems)
    {
       return $lineItems->reduce(function ($carry, $item) {
            return $carry + ($item['quantity'] * $item['price']);
        }, 0);
    }

    /**
     * Check if the given product variant is out of stock in Shopify.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  string  $variantId
     * @return bool
     */
    protected function isOutOfStock(Merchant $merchant, string $variantId)
    {
        $response = Shopify::productVariants(
            $merchant->shopify_domain, data_get($merchant, 'shopify_info.access_token')
        )->findInventory($variantId);

        if ($response->failed()) {
            return false;
        }

        if (!$inventoryItem = $response->json('data.productVariant.inventoryItem')) {
            return false;
        }

        if (!data_get($inventoryItem, 'tracked')) {
            return false;
        }

        $available = collect(data_get($inventoryItem, 'inventoryLevels.edges'))
            ->reduce(function ($carry, $edge) {
                return $carry + data_get($edge, 'node.available', 0);
            }, 0);

        return $available === 0;
    }
}
