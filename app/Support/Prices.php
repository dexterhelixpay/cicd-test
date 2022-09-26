<?php

namespace App\Support;

use App\Support\ShippingFee;
use App\Models\PaymentType;
use Illuminate\Support\Carbon;
use App\Models\ConvenienceType;
use App\Exceptions\BadRequestException;
use App\Models\ShippingMethod;
use App\Models\Voucher;
use Illuminate\Support\Arr;

class Prices
{
     /**
     * Calculate Order Prices
     *
     * @param  string $type
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Customer  $customer
     * @param  \App\Models\Merchant $merchant
     * @param string|int $paymentTypeId
     * @param bool $isAutoCharge
     * @param string $bankCode
     * @param array|object|null $products
     * @param bool $isFromApiCheckout
     * @param bool $isFromConsole
     * @param bool $isShopifyBooking
     * @param string|int $shippingMethodId
     * @param string $voucherCode
     * @param int|double $bankFee
     *
     * @return array
     */
    public static function compute(
        $type = 'before-subscription',
        $order = null,
        $customer = null,
        $merchant = null,
        $paymentTypeId = null,
        $isAutoCharge = false,
        $bankCode = null,
        $products = null,
        $isFromApiCheckout = false,
        $isFromConsole = false,
        $isShopifyBooking = false,
        $shippingMethodId = null,
        $voucherCode = null,
        $bankFee = null
    ) {
        $cardDiscount = $merchant?->card_discount;
        $isFreeDelivery = false;

        $previousBalance = 0;

        if ($type === 'subscribed') {
            $subtotal = $order->products()->sum('total_price') ?: 0;
            if ($order->custom_shipping_fee !== null) {
                $shippingFee = $order->custom_shipping_fee;
            } else {
                $shippingFee = $order->shipping_fee
                    ?: ShippingFee::compute($order->shipping_method_id, $order->products()->get())
                    ?: 0;

            }

            if (
                !are_shippable_products($order->products) &&
                !are_shippable_products($order->products->pluck('product'))
            ) {
                $shippingFee = 0;
            }

            $voucher = $order->voucher;
            $paymentType = $paymentType ?? $order->payment_type_id;
            $isAutoCharge = $isAutoCharge ?? $order->is_auto_charge;
            $previousBalance = $order->previous_balance ?? $previousBalance;
            $bankCode = $bankCode ?? optional($order->bank)->code;
        }

        if ($type === 'before-subscription') {
            $shippingFee = $shippingMethodId
                ? ShippingFee::compute(
                        $shippingMethodId,
                        $isFromConsole
                            ? $products->pluck('attributes')
                            : $products
                    )
                : 0;

            $voucher = $voucherCode
                ? Voucher::where('code',$voucherCode)->first()
                : null;

            if ($isFromApiCheckout) {
                $subtotal = self::computeSubtotal($products);
            } else if ($isFromConsole) {
                $subtotal = $products->sum('attributes.total_price') ?: 0;
                if (!are_shippable_products($products , 'console')) {
                    $shippingFee = 0;
                }
            } else {
                $subtotal = self::computeSubtotal($products);

                if (
                    !are_shippable_products(
                        collect($products)->filter(fn($product) => $product['quantity'] != 0),
                        'storefront'
                    )
                ) {
                    $shippingFee = 0;
                }
            }

        }

        if (
            $voucher &&
            !self::validateVoucher(
                voucher: $voucher,
                order: $order,
                merchant: $merchant,
                subtotal: $subtotal,
                customer: $customer,
                products: $isFromConsole
                    ? $products->pluck('attributes')
                    : $products
            )
        ) {
            $voucher = null;
        }

        if (
            $subtotal == 0 ||
            ($merchant->free_delivery_threshold
            && $subtotal >= $merchant->free_delivery_threshold)
            || ($isShopifyBooking
                && self::estimateDiscount(
                    ($subtotal + $shippingFee),
                    $voucher,
                    $paymentTypeId,
                    $cardDiscount,
                    $products,
                    $customer,
                    $order
                ) >= $subtotal)
        ) {
            $shippingFee = 0;
            $isFreeDelivery = true;
        }

        $totalPrice =  $subtotal + $shippingFee;

        $voucherAmount = 0;
        $productVoucherDiscounts = [];

        if ($voucher) {
            $originalPrice = $totalPrice;

            $products = $isFromConsole
                ? $products->pluck('attributes')
                : $products;

            if ($type === 'subscribed') {
                $products = $order->products()->get();
            }

            $discount = $voucher->computeTotalDiscount([
                    'totalPrice' => $totalPrice,
                    'products' => $products,
                    'customer' => $customer,
                    'order' => $order
                ]);

            $voucherAmount = data_get($discount, 'discount_amount');
            $totalPrice -= $voucherAmount;

            if ($voucher->hasProuductLimits()) {
                $productVoucherDiscounts = data_get($discount, 'product_voucher_discounts');
            }
        }

        $discountAmount = 0;
        $discountLabel = "Discount";

        if (
            $paymentTypeId == PaymentType::CARD
            && $cardDiscount
            && $isAutoCharge
        ) {
            $originalPrice = $subtotal + $shippingFee;
            $discountLabel = "$discountLabel ($cardDiscount%)";
            $discountAmount = round($originalPrice * ($cardDiscount / 100), 2);

            if ($totalPrice) {
                $totalPrice -= $discountAmount;
            }
        }

        if (
            $paymentTypeId != PaymentType::BANK_TRANSFER
            || ($paymentTypeId == PaymentType::BANK_TRANSFER && $bankCode)
        ) {
            $convenienceDetails = ConvenienceFee::calculateOrderPrice(
                $merchant,
                $paymentTypeId,
                $bankCode,
                $totalPrice
            ) ?? null;
        }

        $totalPrice = isset($convenienceDetails)
            ? $convenienceDetails['total_price']
            : $totalPrice;

        $vatAmount = 0;
        if (
            $totalPrice > 0
            && $merchant->is_vat_enabled
        ) {
            $vatAmount = round($totalPrice * 0.12, 2);
            $totalPrice += $vatAmount;

            if (isset($originalPrice)) {
                $originalPrice += $vatAmount;
            }
        }

        if ($merchant->is_outstanding_balance_enabled) {
            $totalPrice += $previousBalance;
            $subtotal += $previousBalance;
        }

        return [
            'bank_fee' => $bankFee,
            'is_free_delivery' => $isFreeDelivery,
            'shipping_fee' => $shippingFee,
            'convenience_fee' => isset($convenienceDetails) && $totalPrice && $totalPrice > 0
                ? $convenienceDetails['convenience_fee']
                : 0,
            'convenience_label' => isset($convenienceDetails)
                ? $convenienceDetails['convenience_label']
                : null,
            'voucher_amount' => $voucherAmount,
            'discount_amount' => $discountAmount,
            'discount_label' => $discountLabel,
            'vat_amount' => $vatAmount,
            'original_price' => ($originalPrice ?? null) ?: null,
            'subtotal_price' => $subtotal,
            'total_price' => $totalPrice && $totalPrice > 0 ? $totalPrice : 0,
            'product_voucher_discounts' => $productVoucherDiscounts
        ];
    }

    /**
     * Validate voucher
     *
     * @param mixed $type
     * @param mixed $voucher
     * @param mixed $order
     * @param mixed $merchant
     * @param mixed $subtotal
     * @param mixed $customer
     *
     * @return bool
     */
    public static function validateVoucher(
        $voucher,
        $order,
        $merchant,
        $subtotal,
        $customer,
        $products = []
    ) {
        if (!$products && $order) {
            $products = $order->products()->get();
        }

        return $voucher->validate(
            code: $voucher->code,
            totalPrice: $subtotal,
            merchantId: $merchant->id,
            customerId: $customer?->id ?? $order?->subscription?->customer?->id,
            order: $order,
            throwError: false,
            products: $products
        );
    }


    /**
     * Compute subtotal
     *
     * @param mixed $products
     *
     * @return mixed
     */
    public static function computeSubtotal($products)
    {
        return $products
            ->reduce(function($carry, $product) {
                if (!$quantity = data_get($product, 'quantity')) {
                    return $carry;
                }

                $price = 0;

                if (data_has($product, 'variantPrice')) {
                    $price = data_get($product, 'variantPrice');
                } elseif (data_has($product, 'recurrencePrice')) {
                    $price = data_get($product, 'recurrencePrice');
                } elseif (data_has($product, 'price')) {
                    $price = data_get($product, 'price');
                }

                return $carry + ($price * $quantity);
            }, 0);
    }

    /**
     * Estimate the discount
     *
     * @return self
     */
    public static function estimateDiscount(
        $totalPrice,
        $voucher,
        $paymentType,
        $cardDiscount,
        $products = null,
        $customer = null,
        $order = null
    ) {
        $totalDiscount = 0;

        if ($voucher) {
            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $totalPrice,
                'products' => $products,
                'customer' => $customer,
                'order' => $order
            ]);

            $totalDiscount = data_get($discount, 'discount_amount');
        }

        if (
            $paymentType == PaymentType::CARD
            && $cardDiscount
        ) {
            $totalDiscount = round($totalPrice * ($cardDiscount / 100), 2);
        }

        return $totalDiscount;
    }
}
