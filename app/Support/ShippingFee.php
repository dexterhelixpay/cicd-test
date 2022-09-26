<?php

namespace App\Support;

use App\Models\PaymentType;
use App\Models\ShippingMethod;
use App\Models\ProductShippingFee;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Exceptions\BadRequestException;

class ShippingFee
{
    /**
     * Compute the shipping fee
     *
     * @param  integer|null  $shippingMethodId
     * @param  array||object  $products
     *
     * @return int|float
     */
    public static function compute($shippingMethodId = null, $products = [])
    {
        $shippingMethod = ShippingMethod::find($shippingMethodId);

        if (!$shippingMethod) return 0;

        $productsShippingFee = collect($products)
            ->reduce(function($carry, $product) use($shippingMethodId) {
                $quantity = data_get($product, 'quantity', 0);

                $productId = data_get($product, 'product_id')
                    ?? data_get($product, 'id');

                if ($variantId = data_get($product, 'variantId')) {
                    $variant = ProductVariant::find($variantId);

                    if ($variant) {
                        $productId = $variant->product_id;
                    }
                }

                $product = Product::find($productId);

                if (!$product || !$quantity || !$product->is_shippable) return $carry + 0;

                $shippingMethod = $product->shippingFees()
                    ->where('shipping_method_id', $shippingMethodId)
                    ->where('is_enabled', true)
                    ->first();

                if (!$shippingMethod) return $carry + 0;

                $priceWithAdditionalQuantities = ($shippingMethod->first_item_price ?: 0)
                    + (($quantity - 1) * ($shippingMethod?->additional_quantity_price ?: 0));

                return $carry + $priceWithAdditionalQuantities;
            }, 0);

        return $productsShippingFee ?: $shippingMethod?->price ?: 0;
    }
}
