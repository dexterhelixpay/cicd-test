<?php

namespace App\Services;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Api\v1\Merchant\SubscriptionController;
use App\Imports\Orders;
use App\Imports\ShopifyOrders;
use App\Models\Checkout;
use App\Models\Country;
use App\Models\Merchant;
use App\Models\PaymentStatus;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Voucher;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class SubscriptionService
{
    /**
     * Create a subscription for the given .
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  array  $subscriptionData
     * @param  array  $customerData
     * @param  array  $productData
     * @param  int|null  $bookingType
     * @param  string|null  $checkoutHash
     * @param  bool  $ignoresInventory
     * @return \App\Models\Subscription
     */
    public function create(
        Merchant $merchant,
        array $subscriptionData,
        array $customerData,
        array $productData,
        ?int $bookingType = null,
        ?string $checkoutHash = null,
        bool $ignoresInventory = false
    ) {
        $customer = (new CustomerService)->create(
            $merchant,
            $customerData['mobile_number'],
            $customerData['country']
                ?? $customerData['country_id']
                ?? $customerData['country_name']
                ?? 'Philippines',
            Arr::except($customerData, [
                'mobile_number', 'country', 'country_id', 'country_name', 'other_info',
            ]),
            $customerData['other_info'] ?? []
        );

        $subscription = $merchant->subscriptions()
            ->make(Arr::except($subscriptionData, 'other_info'))
            ->forceFill([
                'is_console_booking' => $bookingType === Subscription::BOOKING_CONSOLE,
                'is_api_booking' => $bookingType === Subscription::BOOKING_API,
            ])
            ->customer()
            ->associate($customer);

        $subscription->fill([
            'payor' => $subscription->payor ?? $customer->name,
            'billing_address' => $subscription->billing_address ?? $customer->address,
            'billing_barangay' => $subscription->billing_barangay ?? $customer->barangay,
            'billing_city' => $subscription->billing_city ?? $customer->city,
            'billing_province' => $subscription->billing_province ?? $customer->province,
            'billing_zip_code' => $subscription->billing_zip_code ?? $customer->zip_code,
            'billing_country' => $subscription->billing_country ?? $customer->country?->name ?? 'Philippines',
        ]);

        if ($checkoutHash) {
            [$checkoutId] = Hashids::connection('checkout')->decode($checkoutHash);
            $checkout = Checkout::findOrFail($checkoutId);

            $subscription
                ->forceFill($checkout->only([
                    'max_payment_count',
                    'success_redirect_url',
                    'failure_redirect_url',
                ]))
                ->setAttribute('is_checkout_booking', true);

            $checkout->delete();
        }

        if ($metaInfo = $subscriptionData['other_info'] ?? null) {
            $subscription->other_info = $this->formatMetaInfo($merchant, $metaInfo);
        }

        $subscription->save();

        collect($productData)->each(function ($product) use ($subscription) {
            $subscription->products()->make($product)->setTotalPrice()->save();
        });

        if (!$ignoresInventory) {
            (new ProductService)->checkStocks(
                $merchant,
                $subscription->products()->get()->toArray()
            );
        }

        if ($subscription->products()->where('is_shippable', true)->exists()) {
            $subscription->fill([
                'recipient' => $subscription->recipient ?? $customer->name,
                'shipping_address' => $subscription->shipping_address ?? $customer->address,
                'shipping_barangay' => $subscription->shipping_barangay ?? $customer->barangay,
                'shipping_city' => $subscription->shipping_city ?? $customer->city,
                'shipping_province' => $subscription->shipping_province ?? $customer->province,
                'shipping_zip_code' => $subscription->shipping_zip_code ?? $customer->zip_code,
                'shipping_country' => $subscription->shipping_country ?? $customer->country?->name ?? 'Philippines',
            ]);

            $shippingMethod = (new ShippingMethodService)->guess(
                $merchant, $subscription->shipping_province
            );

            $subscription->shippingMethod()->associate($shippingMethod);
        } else {
            $subscription
                ->fill([
                    'recipient' => null,
                    'shipping_address' => null,
                    'shipping_province' => null,
                    'shipping_city' => null,
                    'shipping_barangay' => null,
                    'shipping_zip_code' => null,
                    'shipping_country' => null,
                ])
                ->shippingMethod()
                ->dissociate();
        }

        $subscription
            ->setTotalPrice()
            ->createInitialOrders();

        $order = $subscription->initialOrder()->first();

        if (!$order->total_price) {
            $order->update(['payment_status_id' => PaymentStatus::PAID]);
        } elseif ($subscription->is_api_booking || $subscription->is_console_booking) {
            if ($subscription->created_at->isSameDay($order->billing_date)) {
                $subscription->notifyCustomer(
                    'payment',
                    in_array($merchant->getKey(), setting('CustomMerchants', []))
                );
            } else {
                (new SubscriptionController)->sendPaymentReminder($subscription, $order);
            }
        }

        (new SubscriptionController)->sendNewSubscriberNotification($subscription);

        return $subscription->fresh('customer', 'initialOrder', 'products');
    }

    /**
     * Cancel the given subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return \App\Models\Subscription
     */
    public function cancel(Subscription $subscription)
    {
        if ($subscription->completed_at) {
            throw new Exception('The subscription has already been completed.');
        }

        if ($subscription->cancelled_at) {
            throw new Exception('The subscription has already been cancelled.');
        }

        return DB::transaction(function () use ($subscription) {
            $subscription->cancelled_at = now();
            $subscription->save();

            return $subscription;
        });
    }

    /**
     * Parse the subscriptions for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \Illuminate\Support\Collection
     */
    public function parse(Merchant $merchant, UploadedFile $file)
    {
        if ($merchant->hasShopifyAccount()) {
            return $this->parseFromShopify($merchant, $file);
        }

        ($import =  new Orders($merchant))->import($file);

        $callback = function (Collection $orders) use ($merchant) {
            $order = $orders->first();
            $country = Country::where('code', $order->get('country'))->first();
            $billingDate = Carbon::parse($order->get('billing_date'))->startOfDay();

            $mobileNumber = trim($order->get('mobile_number'));
            $mobileNumber = $country->code === 'PH' ? Str::mobileNumber($mobileNumber) : $mobileNumber;

            $customer = $merchant->customers()
                ->where('mobile_number', $mobileNumber)
                ->where('country_id', $country->getKey())
                ->firstOrNew()
                ->fill(
                    $order
                        ->only([
                            'name',
                            'email',
                            'address',
                            'barangay',
                            'city',
                            'province',
                            'zip_code',
                        ])
                        ->map(function ($attribute) {
                            return trim($attribute);
                        })
                        ->toArray()
                )
                ->setAttribute('mobile_number', $mobileNumber)
                ->country()
                ->associate($country);

            $products = $orders->map(function (Collection $order) use ($merchant, $billingDate) {
                $foundProduct = $merchant->products()->find($order->get('product_id'));
                $variant = null;

                if ($foundProduct) {
                    $variant = $foundProduct->variants()
                        ->withFrequency($order->get('frequency'))
                        ->first();

                    if (!$variant) {
                        $foundProduct->syncDefaultVariant();

                        $variant = $foundProduct->defaultVariant()->first();
                    }
                }

                $product = [
                    'product_id' => optional($foundProduct)->getKey(),
                    'product_variant_id' => optional($variant)->getKey(),
                    'title' => optional($foundProduct)->title ?? $order->get('product_title'),
                    'description' => optional($foundProduct)->title ?? $order->get('product_description'),
                    'price' => optional($variant)->price ?? ($order->get('price') ?: null),
                    'quantity' => $order->get('quantity'),
                    'total_price' => (optional($variant)->price ?? ($order->get('price') ?: null) ?: 0)
                        ? (optional($variant)->price ?? ($order->get('price') ?: null) ?: 0) * ((int) $order->get('quantity'))
                        : null,
                    'is_shippable' => optional($variant)->is_shippable
                        ?? filter_var($order->get('is_product_shippable'), FILTER_VALIDATE_BOOL),
                    'are_multiple_orders_allowed' => optional($foundProduct)->are_multiple_orders_allowed
                        ?? filter_var($order->get('allow_multiple_quantities'), FILTER_VALIDATE_BOOL),
                ];

                if ($foundProduct && $foundProduct->images()->exists()) {
                    $product['images'] = $foundProduct->images()->pluck('image_path')->toArray();
                }

                $paymentSchedule = $order->only('frequency')->toArray();

                switch ($paymentSchedule['frequency']) {
                    case 'weekly':
                    case 'semimonthly':
                        $paymentSchedule['day_of_week'] = $billingDate->dayOfWeek;
                        break;

                    case 'annually':
                        $paymentSchedule['month'] = $billingDate->month;

                    case 'single':
                    case 'monthly':
                    case 'bimonthly':
                    case 'quarterly':
                    case 'semiannual':
                    default:
                        $paymentSchedule['day'] = $billingDate->day;
                }

                return array_merge($product, [
                    'payment_schedule' => $paymentSchedule,
                ]);
            });

            $billingCountry = Country::where('code', $order->get('billing_country'))->first()
                ?? $country;

            $subscription = [
                'payor' => trim($order->get('payor')) ?: $customer->name,
                'billing_address' => trim($order->get('billing_address') ?? '') ?: $customer->address,
                'billing_barangay' => trim($order->get('billing_barangay') ?? '') ?: $customer->city,
                'billing_city' => trim($order->get('billing_city') ?? '') ?: $customer->city,
                'billing_province' => trim($order->get('billing_province') ?? '') ?: $customer->province,
                'billing_country' => $billingCountry->name,
                'billing_zip_code' => preg_replace(
                    '/[\D]/',
                    '',
                    trim($order->get('billing_zip_code') ?? '') ?: $customer->zip_code
                ),
                'delivery_note' => $order->get('delivery_notes'),
            ];

            if ($voucher = $merchant->vouchers()->where('code', $order->get('voucher_code'))->first()) {
                if (
                    !Voucher::validate(
                        code: $voucher->code,
                        merchantId: $merchant->id,
                        customerId: $customer?->id ?? null
                    )
                ) {
                    throw new BadRequestException('This is not an active voucher code');
                } else {
                    $subscription['voucher_id'] = $voucher->id;
                    $subscription['voucher'] = $voucher;
                }
            }

            if ($products->contains('is_shippable', true)) {
                $shippingProvince = trim($order->get('shipping_province') ?? '')
                    ?: $customer->province;

                $shippingCountry = Country::where('code', $order->get('shipping_country'))->first()
                    ?? $country;

                $shippingMethod = $merchant->shippingMethods()
                    ->whereRelation('provinces', 'name', $shippingProvince)
                    ->first();

                $subscription = array_merge($subscription, [
                    'shipping_method_id' => optional($shippingMethod)->getKey(),

                    'recipient' => trim($order->get('recipient')) ?: $customer->name,
                    'shipping_address' => trim($order->get('shipping_address') ?? '') ?: $customer->address,
                    'shipping_barangay' => trim($order->get('shipping_barangay') ?? '') ?: $customer->city,
                    'shipping_city' => trim($order->get('shipping_city') ?? '') ?: $customer->city,
                    'shipping_province' => $shippingProvince,
                    'shipping_country' => $shippingCountry->name,
                    'shipping_zip_code' => preg_replace(
                        '/[\D]/',
                        '',
                        trim($order->get('shipping_zip_code') ?? '') ?: $customer->zip_code
                    ),
                ]);
            } else {
                $subscription = array_merge($subscription, [
                    'shipping_method_id' => null,

                    'recipient' => null,
                    'shipping_address' => null,
                    'shipping_barangay' => null,
                    'shipping_city' => null,
                    'shipping_province' => null,
                    'shipping_country' => null,
                    'shipping_zip_code' => null,
                ]);
            }

            ($subscription = $merchant->subscriptions()->make()->forceFill($subscription))
                ->setAttribute('is_console_booking', true)
                ->setAttribute('billing_date', $order->get('billing_date'))
                ->setAttribute('max_payment_count', $order->get('max_limit'))
                ->setTotalPrice($products, false);

            $subscription['products'] = collect($products)->values();
            $subscription['customer'] = $customer;

            return $subscription;
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->orders
                ->groupBy('group_id')
                ->flatMap(function (Collection $orders, $subscriptionId = '') use ($callback) {
                    if ($subscriptionId === '') {
                        return $orders->chunk(1)->map($callback);
                    }

                    return [$callback($orders)];
                });
        });
    }

     /**
     * Parse the Shopify orders as subscriptions for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \Illuminate\Support\Collection
     */
    public function parseFromShopify(Merchant $merchant, UploadedFile $file)
    {
        if (!$merchant->hasShopifyAccount()) {
            return $this->parse($merchant, $file);
        }

        ($import = new ShopifyOrders($merchant))->import($file);

        $callback = function (Collection $orders) use ($merchant, $import) {
            $order = $orders->first();
            $country = Country::where('code', $order->get('billing_country'))->first();
            $billingDate = Carbon::parse($order->get('created_at'))->startOfDay();

            $mobileNumber = trim($order->get('phone'));
            $mobileNumber = $country->code === 'PH' ? Str::mobileNumber($mobileNumber) : $mobileNumber;

            $customer = $merchant->customers()
                ->where('mobile_number', $mobileNumber)
                ->where('country_id', $country->getKey())
                ->firstOrNew()
                ->fill([
                    'name' => trim($order->get('billing_name')),
                    'mobile_number' => $mobileNumber,
                    'email' => trim($order->get('email')),
                    'address' => trim($order->get('billing_address1')),
                    'barangay' => trim($order->get('billing_street')),
                    'city' => trim($order->get('billing_city')),
                    'province' => trim($order->get('billing_province_name')),
                    'zip_code' => trim($order->get('billing_zip_code')),
                ])
                ->country()
                ->associate($country);

            $products = $orders->map(function (Collection $order) use (
                $merchant, $import, $billingDate
            ) {
                $data = [
                    'description' => null,
                    'images' => null,
                ];

                if ($variant = $import->shopifyVariants->get((int) $order->get('sku_id'))) {
                    $foundProduct = $merchant->products()
                        ->where(function ($query) use ($variant) {
                            $query
                                ->where('shopify_sku_id', data_get($variant, 'product.legacyResourceId'))
                                ->orWhere('shopify_info->legacyResourceId', data_get($variant, 'product.legacyResourceId'));
                        })
                        ->latest()
                        ->first();

                    $foundVariant = ProductVariant::query()
                        ->where('product_id', optional($foundProduct)->getKey())
                        ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                        ->first();

                    $images = collect(data_get($variant, 'product.images.edges', []))
                        ->pluck('node.url')
                        ->toArray();

                    $data = [
                        'product_id' => optional($foundProduct)->getKey(),
                        'product_variant_id' => optional($foundVariant)->getKey(),
                        'description' => data_get($variant, 'product.descriptionHtml'),
                        'images' => $images,
                        'shopify_product_info' => [
                            'id' => data_get($variant, 'legacyResourceId'),
                            'variant_id' => data_get($variant, 'legacyResourceId'),
                            'product_id' => data_get($variant, 'product.legacyResourceId'),
                            'product_title' => data_get($variant, 'product.title'),
                            'images' => $images,
                        ],
                    ];
                } elseif ($product = $import->shopifyProducts->get((int) $order->get('sku_id'))) {
                    $foundProduct = $merchant->products()
                        ->where(function ($query) use ($product) {
                            $query
                                ->where('shopify_sku_id', data_get($product, 'legacyResourceId'))
                                ->orWhere('shopify_info->legacyResourceId', data_get($product, 'legacyResourceId'));
                        })
                        ->latest()
                        ->first();

                    $foundVariant = ProductVariant::query()
                        ->where('product_id', optional($foundProduct)->getKey())
                        ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                        ->first();

                    $images = collect(data_get($product, 'images.edges', []))
                        ->pluck('node.url')
                        ->toArray();

                    $data = [
                        'product_id' => optional($foundProduct)->getKey(),
                        'product_variant_id' => optional($foundVariant)->getKey(),
                        'description' => data_get($product, 'descriptionHtml'),
                        'images' => $images,
                        'shopify_product_info' => [
                            'id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                            'variant_id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                            'product_id' => data_get($product, 'legacyResourceId'),
                            'product_title' => data_get($product, 'title'),
                            'images' => $images,
                        ],
                    ];
                }

                if (Arr::has($data, 'shopify_product_info')) {
                    $data['shopify_product_info'] = array_merge($data['shopify_product_info'], [
                        'quantity' => intval($order->get('lineitem_quantity') ?? 1),
                        'line_price' => (float) $order->get('lineitem_price'),
                    ]);
                }

                $paymentSchedule = $order->only('frequency')->toArray();

                switch ($paymentSchedule['frequency']) {
                    case 'weekly':
                    case 'semimonthly':
                        $paymentSchedule['day_of_week'] = $billingDate->dayOfWeek;
                        break;

                    case 'annually':
                        $paymentSchedule['month'] = $billingDate->month;

                    case 'single':
                    case 'monthly':
                    case 'bimonthly':
                    case 'quarterly':
                    case 'semiannual':
                    default:
                        $paymentSchedule['day'] = $billingDate->day;
                }

                return array_merge([
                    'title' => $order->get('lineitem_name'),
                    'payment_schedule' => $paymentSchedule,
                    'price' => (float) $order->get('lineitem_price') ?: null,
                    'total_price' => ((float) $order->get('lineitem_price') ?: null)
                        ? ((float) $order->get('lineitem_price') * ((int) $order->get('lineitem_quantity')))
                        : null,
                    'quantity' => $order->get('lineitem_quantity'),
                    'are_multiple_orders_allowed' => true,
                    'is_shippable' => filter_var(
                        $order->get('lineitem_requires_shipping'),
                        FILTER_VALIDATE_BOOL
                    ),
                ], $data);
            });

            $billingCountry = Country::where('code', $order->get('billing_country'))->first()
                ?? $country;

            $subscription = [
                'payor' => trim($order->get('billing_name')) ?: $customer->name,
                'billing_address' => trim($order->get('billing_address1') ?? '') ?: $customer->address,
                'billing_barangay' => trim($order->get('billing_street') ?? '') ?: $customer->city,
                'billing_city' => trim($order->get('billing_city') ?? '') ?: $customer->city,
                'billing_province' => trim($order->get('billing_province_name') ?? '') ?: $customer->province,
                'billing_country' => $billingCountry->name,
                'billing_zip_code' => preg_replace(
                    '/[\D]/',
                    '',
                    trim($order->get('billing_zip_code') ?? '') ?: $customer->zip_code
                ),

                'delivery_note' => $order->get('notes'),
            ];

            if ($voucher = $merchant->vouchers()->where('code', $order->get('voucher_code'))->first()) {
                if (
                    !Voucher::validate(
                        code: $voucher->code,
                        merchantId: $merchant->id,
                        customerId: $customer?->id ?? null
                    )
                ) {
                    throw new BadRequestException('This is not an active voucher code');
                } else {
                    $subscription['voucher_id'] = $voucher->id;
                    $subscription['voucher'] = $voucher;
                }
            }

            if ($products->contains('is_shippable', true)) {
                $shippingProvince = trim($order->get('shipping_province_name') ?? '')
                    ?: $customer->province;

                $shippingCountry = Country::where('code', $order->get('shipping_country'))->first()
                    ?? $country;

                $shippingMethod = $merchant->shippingMethods()
                    ->whereRelation('provinces', 'name', $shippingProvince)
                    ->first();

                $subscription = array_merge($subscription, [
                    'shipping_method_id' => optional($shippingMethod)->getKey(),

                    'recipient' => trim($order->get('recipient')) ?: $customer->name,
                    'shipping_address' => trim($order->get('shipping_address') ?? '') ?: $customer->address,
                    'shipping_barangay' => trim($order->get('shipping_barangay') ?? '') ?: $customer->city,
                    'shipping_city' => trim($order->get('shipping_city') ?? '') ?: $customer->city,
                    'shipping_province' => $shippingProvince,
                    'shipping_country' => $shippingCountry->name,
                    'shipping_zip_code' => preg_replace(
                        '/[\D]/',
                        '',
                        trim($order->get('shipping_zip_code') ?? '') ?: $customer->zip_code
                    ),
                ]);
            } else {
                $subscription = array_merge($subscription, [
                    'shipping_method_id' => null,

                    'recipient' => null,
                    'shipping_address' => null,
                    'shipping_barangay' => null,
                    'shipping_city' => null,
                    'shipping_province' => null,
                    'shipping_country' => null,
                    'shipping_zip_code' => null,
                ]);
            }

            ($subscription = $merchant->subscriptions()->make()->forceFill($subscription))
                ->setAttribute('is_console_booking', true)
                ->setAttribute('is_shopify_booking', true)
                ->setAttribute('max_payment_count', $order->get('max_limit'))
                ->setAttribute('billing_date', $order->get('created_at'))
                ->setTotalPrice($products, false);

            $subscription['products'] = collect($products)->values();
            $subscription['customer'] = $customer;

            return $subscription;
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->orders
                ->groupBy('name')
                ->flatMap(function (Collection $orders, $subscriptionId = '') use ($callback) {
                    if ($subscriptionId === '') {
                        return $orders->chunk(1)->map($callback);
                    }

                    return [$callback($orders)];
                });
        });
    }

    /**
     * Import the subscriptions for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \Illuminate\Support\Collection
     */
    public function import(Merchant $merchant, UploadedFile $file)
    {
        if ($merchant->hasShopifyAccount()) {
            return $this->importFromShopify($merchant, $file);
        }

        ($import =  new Orders($merchant))->import($file);

        $callback = function (Collection $orders) use ($merchant) {
            $order = $orders->first();
            $country = Country::where('code', $order->get('country'))->first();
            $billingDate = Carbon::parse($order->get('billing_date'))->startOfDay();

            $mobileNumber = trim($order->get('mobile_number'));
            $mobileNumber = $country->code === 'PH' ? Str::mobileNumber($mobileNumber) : $mobileNumber;

            $customer = $merchant->customers()
                ->where('mobile_number', $mobileNumber)
                ->where('country_id', $country->getKey())
                ->firstOrNew()
                ->fill(
                    $order
                        ->only([
                            'name',
                            'email',
                            'address',
                            'barangay',
                            'city',
                            'province',
                            'zip_code',
                        ])
                        ->map(function ($attribute) {
                            return trim($attribute);
                        })
                        ->toArray()
                )
                ->setAttribute('mobile_number', $mobileNumber)
                ->country()
                ->associate($country);

            $customer->save();

            $products = $orders->map(function (Collection $order) use ($merchant, $billingDate) {
                $foundProduct = $merchant->products()->find($order->get('product_id'));
                $variant = null;

                if ($foundProduct) {
                    $variant = $foundProduct->variants()
                        ->withFrequency($order->get('frequency'))
                        ->first();

                    if (!$variant) {
                        $foundProduct->syncDefaultVariant();

                        $variant = $foundProduct->defaultVariant()->first();
                    }
                }

                $product = [
                    'product_id' => optional($foundProduct)->getKey(),
                    'product_variant_id' => optional($variant)->getKey(),
                    'title' => optional($foundProduct)->title ?? $order->get('product_title'),
                    'description' => optional($foundProduct)->title ?? $order->get('product_description'),
                    'price' => optional($variant)->price ?? ($order->get('price') ?: null),
                    'quantity' => $order->get('quantity'),
                    'is_shippable' => optional($variant)->is_shippable
                        ?? filter_var($order->get('is_product_shippable'), FILTER_VALIDATE_BOOL),
                    'are_multiple_orders_allowed' => optional($foundProduct)->are_multiple_orders_allowed
                        ?? filter_var($order->get('allow_multiple_quantities'), FILTER_VALIDATE_BOOL),
                ];

                if ($foundProduct && $foundProduct->images()->exists()) {
                    $product['images'] = $foundProduct->images()->pluck('image_path')->toArray();
                }

                $paymentSchedule = $order->only('frequency')->toArray();

                switch ($paymentSchedule['frequency']) {
                    case 'weekly':
                    case 'semimonthly':
                        $paymentSchedule['day_of_week'] = $billingDate->dayOfWeek;
                        break;

                    case 'annually':
                        $paymentSchedule['month'] = $billingDate->month;

                    case 'single':
                    case 'monthly':
                    case 'bimonthly':
                    case 'quarterly':
                    case 'semiannual':
                    default:
                        $paymentSchedule['day'] = $billingDate->day;
                 }

                $metaNotes = collect([
                        'product_meta_description_1',
                        'product_meta_description_2',
                        'product_meta_description_3',
                    ])->map(function ($meta) use($order) {
                        return $order->get($meta) ?? null;
                    })->filter()->values();

                if (count($metaNotes)) {
                    $product['sku_meta_notes'] = $metaNotes;
                }

                return array_merge($product, [
                    'payment_schedule' => $paymentSchedule,
                ]);
            });

            $billingCountry = Country::where('code', $order->get('billing_country'))->first()
                ?? $country;

            $subscription = [
                'payor' => trim($order->get('payor')) ?: $customer->name,
                'billing_address' => trim($order->get('billing_address') ?? '') ?: $customer->address,
                'billing_barangay' => trim($order->get('billing_barangay') ?? '') ?: $customer->city,
                'billing_city' => trim($order->get('billing_city') ?? '') ?: $customer->city,
                'billing_province' => trim($order->get('billing_province') ?? '') ?: $customer->province,
                'billing_country' => $billingCountry->name,
                'billing_zip_code' => preg_replace(
                    '/[\D]/',
                    '',
                    trim($order->get('billing_zip_code') ?? '') ?: $customer->zip_code
                ),

                'delivery_note' => $order->get('delivery_notes'),
                'max_payment_count' => $order->get('max_limit')
            ];


            if ($products->contains('is_shippable', true)) {
                $shippingProvince = trim($order->get('shipping_province') ?? '')
                    ?: $customer->province;

                $shippingCountry = Country::where('code', $order->get('shipping_country'))->first()
                    ?? $country;

                $shippingMethod = $merchant->shippingMethods()
                    ->whereRelation('provinces', 'name', $shippingProvince)
                    ->first();

                $subscription = array_merge($subscription, [
                    'shipping_method_id' => optional($shippingMethod)->getKey(),

                    'recipient' => trim($order->get('recipient')) ?: $customer->name,
                    'shipping_address' => trim($order->get('shipping_address') ?? '') ?: $customer->address,
                    'shipping_barangay' => trim($order->get('shipping_barangay') ?? '') ?: $customer->city,
                    'shipping_city' => trim($order->get('shipping_city') ?? '') ?: $customer->city,
                    'shipping_province' => $shippingProvince,
                    'shipping_country' => $shippingCountry->name,
                    'shipping_zip_code' => preg_replace(
                        '/[\D]/',
                        '',
                        trim($order->get('shipping_zip_code') ?? '') ?: $customer->zip_code
                    ),
                ]);
            } else {
                $subscription = array_merge($subscription, [
                    'shipping_method_id' => null,

                    'recipient' => null,
                    'shipping_address' => null,
                    'shipping_barangay' => null,
                    'shipping_city' => null,
                    'shipping_province' => null,
                    'shipping_country' => null,
                    'shipping_zip_code' => null,
                ]);
            }

            if ($voucher = $merchant->vouchers()->where('code', $order->get('voucher_code'))->first()) {
                if (
                    !Voucher::validate(
                        code: $voucher->code,
                        merchantId: $merchant->id,
                        customerId: $customer?->id ?? null
                    )
                ) {
                    throw new BadRequestException('This is not an active voucher code');
                } else {
                    $subscription['voucher_id'] = $voucher->id;
                }
            }

            ($subscription = $merchant->subscriptions()->make()->forceFill($subscription))
                ->setAttribute('is_console_booking', true)
                ->customer()
                ->associate($customer)
                ->save();

            $products
                ->each(function ($product) use ($subscription) {
                    $subscription
                        ->products()
                        ->make($product)
                        ->setTotalPrice()
                        ->save();
                });

            $subscription
                ->refresh()
                ->setTotalPrice()
                ->createInitialOrders($order->get('billing_date') ?? null);

            /** @var \App\Models\Order */
            $initialOrder = $subscription->initialOrder()->first();

            if (!$initialOrder->total_price) {
                $initialOrder
                    ->forceFill(['payment_status_id' => PaymentStatus::PAID])
                    ->update();
            }

            return $subscription->fresh('customer', 'products', 'orders');
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->orders
                ->groupBy('group_id')
                ->flatMap(function (Collection $orders, $subscriptionId) use ($callback) {
                    if ($subscriptionId === '') {
                        return $orders->chunk(1)->map($callback);
                    }

                    return [$callback($orders)];
                });
        });
    }

    /**
     * Import the Shopify orders as subscriptions for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \Illuminate\Support\Collection
     */
    public function importFromShopify(Merchant $merchant, UploadedFile $file)
    {
        if (!$merchant->hasShopifyAccount()) {
            return $this->import($merchant, $file);
        }

        ($import = new ShopifyOrders($merchant))->import($file);

        $callback = function (Collection $orders) use ($merchant, $import) {
            $order = $orders->first();
            $country = Country::where('code', $order->get('billing_country'))->first();
            $billingDate = Carbon::parse($order->get('created_at'))->startOfDay();

            $mobileNumber = trim($order->get('phone'));
            $mobileNumber = $country->code === 'PH' ? Str::mobileNumber($mobileNumber) : $mobileNumber;

            $customer = $merchant->customers()
                ->where('mobile_number', $mobileNumber)
                ->where('country_id', $country->getKey())
                ->firstOrNew()
                ->fill([
                    'name' => trim($order->get('billing_name')),
                    'mobile_number' => $mobileNumber,
                    'email' => trim($order->get('email')),
                    'address' => trim($order->get('billing_address1')),
                    'barangay' => trim($order->get('billing_street')),
                    'city' => trim($order->get('billing_city')),
                    'province' => trim($order->get('billing_province_name')),
                    'zip_code' => trim($order->get('billing_zip_code')),
                ])
                ->country()
                ->associate($country);

            $customer->save();

            $products = $orders->map(function (Collection $order) use (
                $merchant, $import, $billingDate
            ) {
                $data = [
                    'description' => null,
                    'images' => null,
                ];

                if ($variant = $import->shopifyVariants->get((int) $order->get('sku_id'))) {
                    $foundProduct = $merchant->products()
                        ->where(function ($query) use ($variant) {
                            $query
                                ->where('shopify_sku_id', data_get($variant, 'product.legacyResourceId'))
                                ->orWhere('shopify_info->legacyResourceId', data_get($variant, 'product.legacyResourceId'));
                        })
                        ->latest()
                        ->first();

                    $foundVariant = ProductVariant::query()
                        ->where('product_id', optional($foundProduct)->getKey())
                        ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                        ->first();

                    $images = collect(data_get($variant, 'product.images.edges', []))
                        ->pluck('node.url')
                        ->toArray();

                    $data = [
                        'product_id' => optional($foundProduct)->getKey(),
                        'product_variant_id' => optional($foundVariant)->getKey(),
                        'description' => data_get($variant, 'product.descriptionHtml'),
                        'images' => $images,
                        'shopify_product_info' => [
                            'id' => data_get($variant, 'legacyResourceId'),
                            'variant_id' => data_get($variant, 'legacyResourceId'),
                            'product_id' => data_get($variant, 'product.legacyResourceId'),
                            'product_title' => data_get($variant, 'product.title'),
                            'images' => $images,
                        ],
                    ];
                } elseif ($product = $import->shopifyProducts->get((int) $order->get('sku_id'))) {
                    $foundProduct = $merchant->products()
                        ->where(function ($query) use ($product) {
                            $query
                                ->where('shopify_sku_id', data_get($product, 'legacyResourceId'))
                                ->orWhere('shopify_info->legacyResourceId', data_get($product, 'legacyResourceId'));
                        })
                        ->latest()
                        ->first();

                    $foundVariant = ProductVariant::query()
                        ->where('product_id', optional($foundProduct)->getKey())
                        ->where('shopify_variant_id', data_get($variant, 'legacyResourceId'))
                        ->first();

                    $images = collect(data_get($product, 'images.edges', []))
                        ->pluck('node.url')
                        ->toArray();

                    $data = [
                        'product_id' => optional($foundProduct)->getKey(),
                        'product_variant_id' => optional($foundVariant)->getKey(),
                        'description' => data_get($product, 'descriptionHtml'),
                        'images' => $images,
                        'shopify_product_info' => [
                            'id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                            'variant_id' => data_get($product, 'variants.edges.0.node.legacyResourceId'),
                            'product_id' => data_get($product, 'legacyResourceId'),
                            'product_title' => data_get($product, 'title'),
                            'images' => $images,
                        ],
                    ];
                }

                if (Arr::has($data, 'shopify_product_info')) {
                    $data['shopify_product_info'] = array_merge($data['shopify_product_info'], [
                        'quantity' => intval($order->get('lineitem_quantity') ?? 1),
                        'line_price' => (float) $order->get('lineitem_price'),
                    ]);
                }

                $paymentSchedule = $order->only('frequency')->toArray();

                switch ($paymentSchedule['frequency']) {
                    case 'weekly':
                    case 'semimonthly':
                        $paymentSchedule['day_of_week'] = $billingDate->dayOfWeek;
                        break;

                    case 'annually':
                        $paymentSchedule['month'] = $billingDate->month;

                    case 'single':
                    case 'monthly':
                    case 'bimonthly':
                    case 'quarterly':
                    case 'semiannual':
                    default:
                        $paymentSchedule['day'] = $billingDate->day;
                }

                $metaNotes = collect([
                    'product_meta_description_1',
                    'product_meta_description_2',
                    'product_meta_description_3',
                ])->map(function ($meta) use($order) {
                    return $order->get($meta) ?? null;
                })->filter()->values();

                if (count($metaNotes)) {
                    $data['sku_meta_notes'] = $metaNotes;
                }

                return array_merge([
                    'title' => $order->get('lineitem_name'),
                    'payment_schedule' => $paymentSchedule,
                    'price' => (float) $order->get('lineitem_price') ?: null,
                    'quantity' => $order->get('lineitem_quantity'),
                    'are_multiple_orders_allowed' => true,
                    'is_shippable' => filter_var(
                        $order->get('lineitem_requires_shipping'),
                        FILTER_VALIDATE_BOOL
                    )
                ], $data);
            });

            $billingCountry = Country::where('code', $order->get('billing_country'))->first()
                ?? $country;

            $subscription = [
                'payor' => trim($order->get('billing_name')) ?: $customer->name,
                'billing_address' => trim($order->get('billing_address1') ?? '') ?: $customer->address,
                'billing_barangay' => trim($order->get('billing_street') ?? '') ?: $customer->city,
                'billing_city' => trim($order->get('billing_city') ?? '') ?: $customer->city,
                'billing_province' => trim($order->get('billing_province_name') ?? '') ?: $customer->province,
                'billing_country' => $billingCountry->name,
                'billing_zip_code' => preg_replace(
                    '/[\D]/',
                    '',
                    trim($order->get('billing_zip_code') ?? '') ?: $customer->zip_code
                ),

                'delivery_note' => $order->get('notes'),
                'max_payment_count' => $order->get('max_limit')
            ];

            if ($products->contains('is_shippable', true)) {
                $shippingProvince = trim($order->get('shipping_province_name') ?? '')
                    ?: $customer->province;

                $shippingCountry = Country::where('code', $order->get('shipping_country'))->first()
                    ?? $country;

                $shippingMethod = $merchant->shippingMethods()
                    ->whereRelation('provinces', 'name', $shippingProvince)
                    ->first();

                $subscription = array_merge($subscription, [
                    'shipping_method_id' => optional($shippingMethod)->getKey(),

                    'recipient' => trim($order->get('recipient')) ?: $customer->name,
                    'shipping_address' => trim($order->get('shipping_address') ?? '') ?: $customer->address,
                    'shipping_barangay' => trim($order->get('shipping_barangay') ?? '') ?: $customer->city,
                    'shipping_city' => trim($order->get('shipping_city') ?? '') ?: $customer->city,
                    'shipping_province' => $shippingProvince,
                    'shipping_country' => $shippingCountry->name,
                    'shipping_zip_code' => preg_replace(
                        '/[\D]/',
                        '',
                        trim($order->get('shipping_zip_code') ?? '') ?: $customer->zip_code
                    ),
                ]);
            } else {
                $subscription = array_merge($subscription, [
                    'shipping_method_id' => null,

                    'recipient' => null,
                    'shipping_address' => null,
                    'shipping_barangay' => null,
                    'shipping_city' => null,
                    'shipping_province' => null,
                    'shipping_country' => null,
                    'shipping_zip_code' => null,
                ]);
            }

            if ($voucher = $merchant->vouchers()->where('code', $order->get('voucher_code'))->first()) {
                if (
                    !Voucher::validate(
                        code: $voucher->code,
                        merchantId: $merchant->id,
                        customerId: $customer?->id ?? null
                    )
                ) {
                    throw new BadRequestException('This is not an active voucher code');
                } else {
                    $subscription['voucher_id'] = $voucher->id;
                }
            }

            ($subscription = $merchant->subscriptions()->make()->forceFill($subscription))
                ->setAttribute('is_console_booking', true)
                ->setAttribute('is_shopify_booking', true)
                ->setAttribute('max_payment_count', $order->get('max_limit'))
                ->customer()
                ->associate($customer)
                ->save();

            $products
                ->each(function ($product) use ($subscription) {
                    $subscription
                        ->products()
                        ->make($product)
                        ->setTotalPrice()
                        ->save();
                });

            $subscription
                ->refresh()
                ->setTotalPrice()
                ->createInitialOrders();

            /** @var \App\Models\Order */
            $initialOrder = $subscription->initialOrder()->first();

            if (!$initialOrder->total_price) {
                $initialOrder
                    ->forceFill(['payment_status_id' => PaymentStatus::PAID])
                    ->update();
            }

            return $subscription->fresh('customer', 'products', 'orders');
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->orders
                ->groupBy('name')
                ->flatMap(function (Collection $orders, $subscriptionId) use ($callback) {
                    if ($subscriptionId === '') {
                        return $orders->chunk(1)->map($callback);
                    }

                    return [$callback($orders)];
                });
        });
    }

    /**
     * Format the given subscription meta info.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  array<string, array>  $metaInfo
     * @return \Illuminate\Support\Collection
     */
    public function formatMetaInfo(Merchant $merchant, array $metaInfo)
    {
        $merchant->loadMissing('subscriptionCustomFields');

        return collect($metaInfo)
            ->map(function ($info) use ($merchant) {
                if (!$field = $merchant->subscriptionCustomFields->firstWhere('code', $info['code'])) {
                    return null;
                }

                return Arr::except($field->attributesToArray(), ['created_at', 'updated_at'])
                    + Arr::only($info, 'value');
            })
            ->filter(function ($info) {
                return isset($info['value']) && !is_null($info['value']) && $info['value'] !== '';
            })
            ->values();
    }

    /**
     * Format the given subscription products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  array<int, array>  $products
     * @return \Illuminate\Support\Collection
     */
    public function formatProducts(Merchant $merchant, array $products)
    {
        return collect($products)
            ->map(function ($data) use ($merchant) {
                if (!$product = $merchant->products()->find($data['product_id'] ?? null)) {
                    return Arr::except($data, ['product_id', 'product_variant_id']);
                }

                if ($variantId = $data['product_variant_id'] ?? null) {
                    $variant = $product->allVariants()->find($variantId);
                }

                if (empty($variant)) {
                    $frequency = data_get($data, 'payment_schedule.frequency');

                    $variant = $product->allVariants()
                        ->whereHas('optionValues', function ($query) use ($frequency) {
                            $query
                                ->where('value', $frequency)
                                ->whereHas('option', function ($query) {
                                    $query->where('code', 'recurrence');
                                });
                        })
                        ->first();
                }

                if (empty($variant)) {
                    $product->syncDefaultVariant();

                    $variant = $product->defaultVariant()->first();
                }

                $data = array_merge($data, [
                    'product_variant_id' => $variant->getKey(),
                    'are_multiple_orders_allowed' => $product->are_multiple_orders_allowed,
                    'is_shippable' => $variant->is_shippable,
                ]);

                if (!data_get($data, 'title')) {
                    $data['title'] = $product->title;
                }

                if (!data_get($data, 'description')) {
                    $data['description'] = $product->description;
                }

                if (!Arr::has($data, 'price')) {
                    $data['price'] = $variant->price;
                }

                if (!data_get($data, 'images') && $product->images()->exists()) {
                    $data['images'] = $product->images()->pluck('image_path')->toArray();
                }

                if ($propeties = data_get($data, 'product_properties')) {
                    data_set(
                        $data,
                        'product_properties',
                        collect($propeties)
                            ->map(function ($property) use ($merchant, $product) {
                                if (!$productProperty = $product->properties->firstWhere('title', $property['title'])) {
                                    return null;
                                }

                                return Arr::except($productProperty->toArray(), ['created_at', 'updated_at'])
                                    + Arr::only($property, 'value');
                            })
                            ->filter()
                            ->toArray()
                    );
                }

                return $data;
            });
    }
}
