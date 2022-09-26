<?php

namespace App\Models;

use App\Facades\Viber;
use App\Facades\PayMaya;
use App\Traits\SetEditUrl;
use App\Traits\TracksEmail;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Libraries\PayMaya\Card;
use App\Support\ConvenienceFee;
use App\Services\PaymentService;
use App\Support\PaymentSchedule;
use App\Libraries\PayMaya\Wallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use App\Notifications\Order\OrderPaid;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Order\OrderFailed;
use App\Notifications\Order\OrderPayment;
use App\Notifications\Order\OrderShipped;
use App\Notifications\Order\OrderSkipped;
use App\Notifications\Order\OrderUpdated;
use App\Notifications\Order\OrderCancelled;
use App\Notifications\Order\OrderConfirmed;
use App\Notifications\DynamicSmsNotification;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Libraries\Viber\Message as ViberMessage;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use App\Notifications\NewSubscriberEmailNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Libraries\PayMaya\Customer as PaymayaCustomer;
use App\Support\ShippingFee;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends RecordableModel
{
    use HasFactory, SoftDeletes, SetEditUrl, TracksEmail;

    /**
     * Constant representing a subscription created from the API.
     *
     * @var int
     */
    const BOOKING_API = 1;

    /**
     * Constant representing a subscription created from the console.
     *
     * @var int
     */
    const BOOKING_CONSOLE = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',
        'payment_type_id',
        'bank_id',
        'shipping_method_id',

        'paymaya_link_id',
        'paymaya_payment_token_id',
        'paymaya_card_type',

        'payment_schedule',
        'original_price',
        'total_price',
        'shipping_fee',
        'custom_shipping_fee',
        'bank_fee',
        'vat_amount',
        'convenience_fee',

        'payor',
        'billing_address',
        'billing_province',
        'billing_city',
        'billing_barangay',
        'billing_zip_code',
        'billing_country',

        'recipient',
        'shipping_address',
        'shipping_province',
        'shipping_city',
        'shipping_barangay',
        'shipping_zip_code',
        'shipping_country',

        'reference_id',
        'max_payment_count',

        'cancelled_at',

        'is_shopify_booking',
        'is_membership',
        'is_auto_charge',
        'is_deep_link_checkout',

        'delivery_note',

        'voucher_id',

        'max_payment_count',
        'paymaya_wallet_customer_name',
        'paymaya_wallet_mobile_number',

        'other_info',

        'discord_user_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'payment_schedule' => 'array',
        'is_console_booking' => 'boolean',
        'is_deep_link_checkout' => 'boolean',
        'is_api_booking' => 'boolean',
        'is_checkout_booking' => 'boolean',
        'is_shopify_booking' => 'boolean',
        'is_membership' => 'boolean',
        'is_auto_charge' => 'boolean',
        'custom_shipping_fee' => 'double',
        'other_info' => 'array',
    ];

    /**
     * The attributes that are considered as email type
     *
     * @var string
     */
    protected $emailType = EmailEvent::SUBSCRIPTION;

    /**
     * Get email info
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function email(): MorphOne
    {
        return $this->morphOne(Email::class, 'model');
    }

    /**
     * Get the subscribed customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the first order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function initialOrder(): HasOne
    {
        return $this->hasOne(Order::class)->ofMany('id', 'min');
    }

    /**
     * Get the last paid order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestPaidOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->ofMany([
                'billing_date' => 'max',
                'id' => 'max',
            ], function ($query) {
                $query->where('order_status_id', OrderStatus::PAID);
            });
    }

    /**
     * Get the last paid order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function lastPaidOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->where('order_status_id', OrderStatus::PAID)
            ->latest();
    }

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the next transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function nextOrder(): HasOne
    {
        return $this->hasOne(Order::class)->with('products')->ofMany('billing_date');
    }

    /**
     * Get the next unpaid transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function nextUnpaidOrder(): HasOne
    {
        return $this->hasOne(Order::class)
            ->whereIn('order_status_id', [
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED
            ]);
    }

    /**
     * Get the next transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function nextOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->whereIn('order_status_id',[
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED
            ])
            ->where('billing_date', '<>', function ($query) {
                $query
                    ->selectRaw('min(`billing_date`)')
                    ->from('orders as o')
                    ->whereColumn('o.subscription_id', 'orders.subscription_id');
            });
    }

    /**
     * Get the current transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class)
            ->ofMany(['billing_date' => 'max'], function ($query) {
                $query
                    ->join('subscriptions', 'orders.subscription_id', 'subscriptions.id')
                    ->where(function ($query) {
                        $query
                            ->where('max_payment_count', 1)
                            ->orWhere(function ($query) {
                                $query
                                    ->where(function ($query) {
                                        $query
                                            ->whereNull('max_payment_count')
                                            ->orWhere('max_payment_count', '>', 1);
                                    })
                                    ->where('billing_date', '<', function ($query) {
                                        $query
                                            ->selectRaw('max(`o`.`billing_date`)')
                                            ->from('orders as o')
                                            ->whereColumn('o.subscription_id', 'orders.subscription_id');
                                    });
                            });
                    });
            });
    }

    /**
     * Get the orders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the attachments.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get the payment type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    /**
     * Get the recent transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recentOrders(): HasMany
    {
        return $this->hasMany(Order::class)
            ->where('billing_date', '<', function ($query) {
                $query
                    ->selectRaw('max(`billing_date`)')
                    ->from('orders as o')
                    ->whereColumn('o.subscription_id', 'orders.subscription_id');
            });
    }

    /**
     * Get the subscribed products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(SubscribedProduct::class);
    }

    /**
     * Get the shipping method for the products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get the voucher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the shipping method for the products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Get the subscription import.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscriptionImport(): BelongsTo
    {
        return $this->belongsTo(SubscriptionImport::class);
    }

    /**
     * Get the schedule email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function scheduleEmail(): BelongsTo
    {
        return $this->belongsTo(ScheduleEmail::class);
    }

    /**
     * Scope a query to only include active subscriptions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query
            ->whereNull('cancelled_at')
            ->whereRelation('orders', 'order_status_id', OrderStatus::PAID)
            ->where(function ($query) {
                $query
                    ->whereNull('completed_at')
                    ->orWhere(function ($query) {
                        $query
                            ->whereNotNull('completed_at')
                            ->whereRelation('orders', 'payment_schedule->frequency', 'single');
                    });
            });
    }

    /**
     * Check if the all orders have been paid.
     *
     * @return bool
     */
    public function hasPaidAllOrders()
    {
        $maxPaymentCount = $this->max_payment_count ?? PHP_INT_MAX;

        return $this->products()->get()
            ->groupBy('group_number')
            ->sortKeys()
            ->reject(function ($products, $groupNumber) use ($maxPaymentCount) {
                $paymentSchedule =  $products->first()->payment_schedule;
                $maxPaymentCount = $paymentSchedule['frequency'] === 'single'
                    ? 1
                    : $maxPaymentCount;

                $paidOrderCount =  $this->orders()
                    ->where(function ($query) use ($groupNumber) {
                        $query->whereNull('group_number')->orWhere('group_number', $groupNumber);
                    })
                    ->where('order_status_id', OrderStatus::PAID)
                    ->count();

                return $paidOrderCount >= $maxPaymentCount;
            })
            ->isEmpty();
    }

    /**
     * Check if the subscription is cancelled.
     *
     * @return bool
     */
    public function isCancelled()
    {
        return !is_null($this->cancelled_at);
    }

    /**
     * Check if the subscription has other info.
     *
     * @return bool
     */
    public function hasOtherInfo()
    {
        return collect($this->other_info)
            ->contains(function ($info) {
                $value = data_get($info, 'value');

                return $value !== null && $value !== '';
            });
    }

    /**
     * Check if customer's card is vaulted.
     *
     * @return bool
     */
    public function isCardVaulted()
    {
        if (!$this->customer->paymaya_uuid || !$this->paymaya_card_token_id) {
            return false;
        }

        return PayMaya::withVaultKeys(
            $this->merchant->paymaya_vault_console_public_key ?? $this->merchant->paymaya_vault_public_key,
            $this->merchant->paymaya_vault_console_secret_key ?? $this->merchant->paymaya_vault_secret_key,
            function () {
                return (new PaymayaCustomer($this->customer->paymaya_uuid))
                    ->findCard($this->paymaya_card_token_id)
                    ->then(function ($card) {
                        return data_get($card, 'walletType') === 'VAULTED';
                    }, function () {
                        return false;
                    })
                    ->wait();
            }
        );
    }

    /**
     * Check if the subscription is completed.
     *
     * @return bool
     */
    public function isCompleted()
    {
        return !is_null($this->completed_at);
    }

    /**
     * Check if customer's PayMaya wallet is verified.
     *
     * @param  bool  $withUpdate
     * @return bool
     */
    public function isWalletVerified($withUpdate = false)
    {
        if (!$this->paymaya_link_id) {
            return false;
        }

        return PayMaya::withPwpKeys(
            $this->merchant->paymaya_pwp_console_public_key ?? $this->merchant->paymaya_pwp_public_key,
            $this->merchant->paymaya_pwp_console_secret_key ?? $this->merchant->paymaya_pwp_secret_key,
            function () use ($withUpdate) {
                return Wallet::find($this->paymaya_link_id)
                    ->then(function ($wallet) use ($withUpdate) {
                        if ($withUpdate) {
                            $name = join(' ', [
                                data_get($wallet, 'customer.firstName'),
                                data_get($wallet, 'customer.lastName')
                            ]);

                            $this->forceFill([
                                'paymaya_wallet_customer_name' => $name,
                                'paymaya_wallet_mobile_number' => data_get($wallet, 'customer.contact.phone')
                            ])->saveQuietly();

                            $this->customer->wallets()
                                ->firstOrNew([
                                    'mobile_number' => data_get($wallet, 'customer.contact.phone'),
                                ], [
                                    'name' => $name,
                                    'link_id' => $this->paymaya_link_id,
                                ])
                                ->verify()
                                ->touch();
                        }

                        return data_get($wallet, 'card.state') === 'VERIFIED';
                    }, function ($e) {
                        return false;
                    })
                    ->wait();
            }
        );
    }

    /**
     * Obfuscate the subscription's key.
     *
     * @return string
     */
    public function obfuscateKey()
    {
        return Carbon::parse($this->created_at)->format('ys') . $this->getKey();
    }

     /**
     * Calculate the total price
     *
     * @return self
     */
    public static function calculateTotalPrice(
        $merchant,
        $products,
        $paymentType,
        $shippingFee
    ) {
        $totalPrice = (collect($products)->sum('total_price') ?: 0) + $shippingFee;

        if (
            ($cardDiscount = $merchant->card_discount)
            && $paymentType == PaymentType::CARD
        ) {
            $totalPrice = round($totalPrice * ((100 - $cardDiscount) / 100), 2);
        }

        if (
            $totalPrice > 0
            && ($convenienceFee = $merchant->convenience_fee)
        ) {
            if ($merchant->convenience_type_id === ConvenienceType::FIXED) {
                $totalPrice += $convenienceFee;
            } else {
                $totalPrice += $totalPrice * ($convenienceFee / 100);
            }
        }

        return $totalPrice;
    }

    /**
     * Create the initial orders of the subscription.
     *
     * @param string|null $billingDate
     * @return self
     */
    public function createInitialOrders($billingDate = null)
    {
        $this->products()->get()
            ->groupBy(function (SubscribedProduct $product) {
                return PaymentSchedule::toKeyString($product->payment_schedule);
            })
            ->tap(function (Collection $products) use ($billingDate) {
                if ($this->is_console_booking || $this->is_api_booking) {
                    $date = collect($products)
                        ->map(function($frequencyProducts) {
                            $product = $frequencyProducts->values()->first();

                            return collect(PaymentSchedule::getBillingCycle($product->payment_schedule, $this->created_at))
                                ->first();
                        })
                        ->sort()
                        ->values()
                        ->first();
                } else {
                    $date = $this->created_at->toDateString();
                }

                $schedule = $products->count() === 1
                    ? $products->flatten(1)->first()->payment_schedule
                    : null;

                $order = $this->orders()
                    ->make($this->toArray())
                    ->fill([
                        'billing_date' => $billingDate ?: $date,
                        'payment_schedule' => $schedule,
                    ])
                    ->setAttribute('group_number', $products->count() > 1 ? null : 1);

                $order->save();

                $products->flatten(1)->each(function (SubscribedProduct $product) use ($order) {
                    ($orderedProduct = $order->products()->make($product->toArray()))
                        ->subscribedProduct()
                        ->associate($product);

                    if ($product->max_discounted_order_count) {
                        $orderedProduct->forceFill([
                            'price' => $product->discounted_price,
                            'is_discounted' => true,
                        ]);
                    }

                    $orderedProduct->setTotalPrice()->save();
                });

                if ($this->voucher) {
                    $this->voucher->use($order);
                }

                $order->setTotalPrice();
            })
            ->values()
            ->each(function (Collection $products, $index) use($billingDate) {
                $groupNumber = $index + 1;

                $schedule = $products->first()->payment_schedule;
                $maxPaymentCount = $schedule['frequency'] === 'single'
                    ? 1
                    : ($this->max_payment_count ?? PHP_INT_MAX);

                $dates = collect(PaymentSchedule::getBillingCycle(
                        $schedule,
                        $billingDate ?: $this->created_at
                    ))
                    ->take($maxPaymentCount)
                    ->skip(1);

                $products->map(function (SubscribedProduct $product) use ($groupNumber) {
                    $product->setAttribute('group_number', $groupNumber)->saveQuietly();
                });

                $dates->each(function ($date) use ($products, $schedule, $groupNumber) {
                    $order = $this->orders()
                        ->make(Arr::except($this->toArray(), ['voucher_id', 'voucher_code']))
                        ->fill([
                            'billing_date' => $date,
                            'payment_schedule' => $schedule,
                        ])
                        ->setAttribute('group_number', $groupNumber);

                    $order->save();

                    $products->each(function (SubscribedProduct $product) use ($order) {
                        ($orderedProduct = $order->products()->make($product->toArray()))
                            ->subscribedProduct()
                            ->associate($product);

                        if ($product->max_discounted_order_count > 1) {
                            $orderedProduct->forceFill([
                                'price' => $product->discounted_price,
                                'is_discounted' => true,
                            ]);
                        }

                        $orderedProduct->setTotalPrice()->save();
                    });

                    $this->cascadeVoucher($order);

                    $order->setTotalPrice();
                });
            });

        return $this;
    }

    /**
     * Create an order.
     *
     * @param  array  $orderData
     * @param  int|null  $groupNumber
     * @param  bool  $applyVoucher
     * @return \App\Models\Order
     */
    public function createOrder($orderData, $groupNumber = null, $applyVoucher = false)
    {
        $subData = $this->toArray();

        if (!$applyVoucher) {
            $subData = Arr::except($subData, ['voucher_id', 'voucher_code']);
        }

        $order = $this->orders()
            ->make($subData)
            ->forceFill($orderData)
            ->setAttribute('group_number', $groupNumber);

        $order->save();

        $order->syncProductsFromSubscription(
            $this->products()
                ->when($groupNumber, function ($query, $groupNumber) {
                    $query->where('group_number', $groupNumber);
                })
                ->get()
        );

        $this->cascadeVoucher($order);

        return $order->setTotalPrice()->fresh();
    }

    /**
     * Get the latest order from the given group number.
     *
     * @param  int  $groupNumber
     * @return \App\Models\Order
     */
    public function getLatestOrder($groupNumber)
    {
        return $this->orders()
            ->where('group_number', $groupNumber)
            ->orderBy('billing_date', 'desc')
            ->first();
    }

    /**
     * Map the given product data with the correct keys.
     *
     * @param  array  $products
     * @param  \App\Models\Order|null  $order
     * @return array
     */
    public function mapProductData($products, $order = null)
    {
        return collect($products)
            ->map(function ($product) use ($order) {
                if (
                    Arr::has($product, 'attributes.id')
                    && !Arr::has($product, 'attributes.product_id')
                ) {
                    data_set($product, 'attributes.product_id', data_get($product, 'attributes.id'));
                    unset($product['attributes']['id']);
                }

                if (
                    Arr::has($product, 'attributes.product_id')
                    && !Arr::has($product, 'id')
                    && !Arr::has($product, 'attributes.id')
                ) {
                    $subscribedProduct = $this->products()
                        ->where('product_id', data_get($product, 'attributes.product_id'))
                        ->when(!$order, function ($query) use($product) {
                            $query->where(
                                'payment_schedule->frequency',
                                data_get($product, 'attributes.payment_schedule.frequency')
                            );
                        })
                        ->when(data_get($product, 'attributes.product_variant_id'), function ($query) use($product) {
                            $query->where(
                                'product_variant_id',
                                data_get($product, 'attributes.product_variant_id')
                            );
                        })
                        ->where('product_properties',
                            collect(data_get($product, 'attributes.product_properties'))
                                ->map(function($property) {
                                    unset($property['$_jsonApi']);
                                    return $property;
                                })
                                ->toJson()
                        )
                        ->first();
                    if ($subscribedProduct) {
                        data_set($product, 'id', $subscribedProduct->getKey());
                    }
                }

                if (
                    Arr::has($product, 'attributes.product_id')
                    && !Arr::has($product, 'id')
                    && Arr::has($product, 'attributes.id')
                ) {
                    $orderedProduct = OrderedProduct::findOrFail(data_get($product, 'attributes.id'));
                    $subscribedProduct = $orderedProduct?->subscribedProduct;

                    if ($subscribedProduct) {
                        data_set($product, 'id', $subscribedProduct->getKey());
                    }
                }

                if (
                    $this->is_shopify_booking
                    && ($shopifyProductId = data_get($product, 'attributes.shopify_product_info.id'))
                    && !Arr::has($product, 'id')
                ) {
                    $subscribedProduct = $this->products()
                        ->where(function ($query) use ($shopifyProductId) {
                            $query
                                ->where('product_id', $shopifyProductId)
                                ->orWhere('shopify_product_info->id', $shopifyProductId);
                        })
                        ->when(data_get($product, 'attributes.product_variant_id'), function ($query) use($product) {
                            $query->where(
                                'product_variant_id',
                                data_get($product, 'attributes.product_variant_id')
                            );
                        })
                        ->first();

                    if ($subscribedProduct) {
                        data_set($product, 'id', $subscribedProduct->getKey());
                    }
                }

                if ($order) {
                    $shopifyProduct = $this->is_shopify_booking
                        ? $order->products()->where('product_id', $shopifyProductId)->doesntExist()
                        : true;

                    if (
                        $shopifyProduct
                        && $order->products()
                            ->where('product_id', data_get($product, 'attributes.product_id'))
                            ->doesntExist()
                    ) {
                        unset($product['id']);
                    }
                }

                $product['attributes'] = Arr::only(
                    $product['attributes'],
                    (new SubscribedProduct)->getFillable()
                );

                return $product;
            })
            ->toArray();
    }

    /**
     * Estimate the discount
     *
     * @return self
     */
    public function estimateDiscount()
    {
        $subtotal = $this->products()->sum('total_price') ?: 0;

        if ($this->wasChanged('shipping_method_id')) {
            $shippingFee = optional($this->shippingMethod)->price;
        } elseif ($this->custom_shipping_fee !== null) {
            $shippingFee = $this->custom_shipping_fee;
        } else {
            $shippingFee = $this->shipping_fee
                ?: optional($this->shippingMethod)->price
                ?: 0;
        }

        if (!are_shippable_products($this->products->pluck('product'))) {
            $shippingFee = 0;
        }

        $totalPrice = $subtotal + $shippingFee;
        $totalDiscount = 0;

        if ($voucher = $this->voucher()->first()) {
            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $totalPrice,
                'products' => $this->products()->get(),
                'customer' => $this->customer,
                'order' => null
            ]);

            $totalDiscount += data_get($discount, 'discount_amount');
        }

        if (
            $this->payment_type_id == PaymentType::CARD
            && ($cardDiscount = $this->merchant->card_discount)
        ) {
            $originalPrice = $subtotal + $shippingFee;
            $totalDiscount += round($originalPrice * ($cardDiscount / 100), 2);
        }

        return $totalDiscount;
    }

    /**
     * Set the total price based on the subscribed products and shipping method.
     *
     * @param  \Illuminate\Contracts\Support\Arrayable|array|null  $products
     * @param  bool  $persist
     * @return $this
     */
    public function setTotalPrice($products = null, $persist = true)
    {
        $products = $products ? collect($products) : $this->products()->get();
        $subtotal = $products->sum('total_price') ?: 0;

        if ($this->wasChanged('shipping_method_id')) {
            $shippingFee = ShippingFee::compute($this->shipping_method_id, $products);
        } elseif ($this->custom_shipping_fee !== null) {
            $shippingFee = $this->custom_shipping_fee;
        } else {
            if (
                ($shippingMethod = $this->merchant->shippingMethods->first())
                && !$this->shippingMethod
            ) {
                if ($this->shipping_province != 'Metro Manila') {
                    $shippingMethod = $this->merchant->shippingMethods()
                        ->whereRelation('provinces', 'name', $this->shipping_province)
                        ->first() ?: $this->merchant->shippingMethods->last();
                }
            }

            $shippingFee = $this->shipping_fee
                ?: ShippingFee::compute($this->shipping_method_id, $products);

            if (!isset($shippingFee)) {
                $shippingFee = $shippingMethod?->price;
            }
        }

        if (
            $subtotal == 0 ||
            ($this->merchant->free_delivery_threshold
            && $subtotal >= $this->merchant->free_delivery_threshold)
            || ($this->is_shopify_booking && $this->estimateDiscount() >= $subtotal)
            || !are_shippable_products($products)
        ) {
            $shippingFee = 0;
        }

        $totalPrice = $subtotal + $shippingFee;

        if ($voucher = $this->voucher()->first()) {
            $originalPrice = $totalPrice;

            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $totalPrice,
                'products' => $this->products()->get(),
                'customer' => $this->customer,
                'order' => null
            ]);

            $totalPrice -= data_get($discount, 'discount_amount');
        }

        if (
            $this->payment_type_id == PaymentType::CARD
            && $this->is_auto_charge
            && ($cardDiscount = $this->merchant->card_discount)
        ) {
            $originalPrice = $subtotal + $shippingFee;
            $totalPrice -= round($originalPrice * ($cardDiscount / 100), 2);
        }

        if (
            $this->payment_type_id == PaymentType::BANK_TRANSFER && $this->bank
            || $this->payment_type_id != PaymentType::BANK_TRANSFER
        ) {
            $convenienceDetails = ConvenienceFee::getConvenienceFee(
                $this->merchant,
                $this->payment_type_id,
                $this->bank?->code ?? null,
            );
        }

        if (
            isset($convenienceDetails)
            && $totalPrice > 0
            && ($convenienceFee = $convenienceDetails['convenience_fee'])
        ) {
            if ($convenienceDetails['convenience_type_id'] === ConvenienceType::FIXED) {
                $totalPrice += $convenienceFee;
            } else {
                $totalPrice += $totalPrice * ($convenienceFee / 100);
            }

            if (isset($originalPrice)) {
                $originalPrice += $convenienceFee;
            }
        }

        if (
            $totalPrice > 0
            && $this->merchant->is_vat_enabled
        ) {
            $vatAmount = $totalPrice * 0.12;
            $totalPrice += $vatAmount;

            if (isset($originalPrice)) {
                $originalPrice += $vatAmount;
            }
        }

        if ($this->merchant->is_outstanding_balance_enabled) {
            $order = $this->orders()
                ->has('overdueOrders')
                ->first();

            $totalPrice += $order
                ? $order->previous_balance
                : 0;
        }

        if (is_numeric($totalPrice)) {
            $totalPrice = max($totalPrice, 0);
        }

        $this->forceFill([
            'bank_fee' => optional($this->bank)->fee ?: null,
            'shipping_fee' => isset($shippingFee) ? $shippingFee : null,
            'original_price' => ($originalPrice ?? null) ?: null,
            'total_price' => $totalPrice ?: null,
            'vat_amount' => ($vatAmount ?? null) ?: null,
            'convenience_fee' => ($convenienceFee ?? null) ?: null,
        ]);

        return tap($this, fn () => $persist && $this->saveQuietly());
    }

    /**
     * Save the given shopify images.
     *
     * @param  mixed $files
     * @param string $level
     * @return void
     */
    public function saveShopifyImages($files, $order = null)
    {
       collect($files['data']['relationships']['products']['data'])
            ->each(function ($images, $index) use($order) {
                $requestProduct = request()->input("data.relationships.products.data.{$index}");

                if ($order) {
                    $id = data_get($requestProduct, 'attributes.subscribed_product_id');
                    $hasId = Arr::has($requestProduct, 'attributes.subscribed_product_id');
                    $model = $order;
                } else {
                    $id = data_get($requestProduct, 'id');
                    $hasId =  Arr::has($requestProduct, 'id');
                    $model = $this;
                }

                $subscribedProduct = $hasId
                    ? SubscribedProduct::find($id)
                    : $model->products()->where(
                            'product_id',
                            data_get($requestProduct, 'attributes.product_id')
                        )->first();

                if (!$subscribedProduct) return;

                $shopifyImages = collect($images['attributes']['shopify_links'])
                    ->map(function (UploadedFile $file, $index) {
                        $path = $this->uploadShopifyLink($file);

                        return [
                            'label' =>  $file->getClientOriginalName(),
                            'field_name' => "uploadery_{$index}",
                            'path' => Storage::url($path),
                            'file_type' => shopify_link_file_type(
                                Str::of($path)->explode('.')->last()
                            )
                        ];
                    })->values()->all();

                $currentShopifyLinks = data_get($requestProduct, 'attributes.shopify_custom_links');

                $links = $currentShopifyLinks
                    ? array_merge(
                            data_get($requestProduct, 'attributes.shopify_custom_links'),
                            $shopifyImages
                        )
                    : $shopifyImages;

                $subscribedProduct->forceFill(['shopify_custom_links' => $links])->update();

                OrderedProduct::where('subscribed_product_id', $subscribedProduct->getKey())
                    ->update([
                        'shopify_custom_links' => $links
                    ]);
            });
    }

    /**
     * Upload the given shopify link
     *
     * @param  \Illuminate\Http\UploadedFile  $link
     * @return string
     */
    public function uploadShopifyLink($link)
    {
        $directory = 'images/merchants/shopify/images';
        $fileRoot = str_replace('-', '_', (string) Str::uuid());
        $path = "{$directory}/{$fileRoot}.{$link->getClientOriginalExtension()}";

        $link = $link instanceof UploadedFile
            ? $link->getContent()
            : $link;

        Storage::put($path, $link);

        return $path;
    }


    /**
     * Sync the subscribed products.
     *
     * @param  array  $products
     * @param  \App\Models\Order|null  $order
     * @return self
     */
    public function syncSubscribedProducts($products, $order = null)
    {
        $productIds = collect($products)->pluck('id')->filter()->all();
        $removedProducts = $this->products()->whereKeyNot($productIds)
            ->when(optional($order)->group_number, function ($query, $groupNumber) {
                $query->where('group_number', $groupNumber);
            })
            ->get();

        $removedProductKeys = $removedProducts->modelKeys();

        $this->orders()
            ->with('products')
            ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::INCOMPLETE, OrderStatus::FAILED])
            ->whereHas('products', function ($query) use ($removedProductKeys) {
                $query->whereIn('subscribed_product_id', $removedProductKeys);
            })
            ->when(optional($order)->group_number, function ($query, $groupNumber) {
                $query->where(function ($query) use ($groupNumber) {
                    $query
                        ->whereNull('group_number')
                        ->orWhere('group_number', $groupNumber);
                });
            })
            ->get()
            ->each(function (Order $order) use ($removedProductKeys) {
                $productKeys = $order->products->pluck('subscribed_product_id');

                if ($productKeys->diff($removedProductKeys)->isEmpty()) {
                    return $order->delete();
                }

                $order->products->whereIn('subscribed_product_id', $removedProductKeys)
                    ->each(function (OrderedProduct $product) {
                        $product->delete();
                    });
            });

        $removedProducts->each->delete();

        collect($products)
            ->map(function ($product) {
                $id = data_get($product, 'id');
                $attributes = data_get($product, 'attributes', []);
                $variant_id = data_get($attributes, 'product_variant_id');

                $variant = ProductVariant::with('optionValues')
                    ->when($variant_id, function($query) use ($attributes){
                        return $query->whereKey(data_get($attributes, 'product_variant_id'));
                    }, function($query) use ($attributes){
                        return $query->where('title', data_get($attributes, 'payment_schedule.frequency'))
                            ->where('product_id', data_get($attributes, 'product_id'))
                            ->orWhere(function($query) use ($attributes) {
                                $query->where('product_id', data_get($attributes, 'product_id'))
                                    ->where('is_default', true);
                            });
                    })
                    ->first();

                $attributes['product_variant_id'] = optional($variant)->id;
                $attributes['option_values'] = optional($variant)->product
                    ? optional($variant)->mapOptionValues()
                    : ($attributes['option_values'] ?? null);

                if (
                    !$id
                    && $productWithSameFrequency = $this->products()->where(
                        'payment_schedule->frequency',
                        data_get($attributes, 'payment_schedule.frequency')
                    )->first()
                ) {
                    $attributes['group_number'] = $productWithSameFrequency->group_number;
                }

                $subscribedProduct = $id
                    ? $this->products()->findOrFail($id)->fill($attributes)
                    : $this->products()->make()->forceFill($attributes);

                $this->orders()
                    ->whereHas('products', function($query) use ($subscribedProduct){
                        $query->where('product_id',$subscribedProduct->product_id);
                    })
                    ->whereIn('order_status_id', [
                        OrderStatus::UNPAID,
                        OrderStatus::INCOMPLETE,
                        OrderStatus::FAILED
                    ])
                    ->get()
                    ->each(function($order) use ($subscribedProduct) {
                        if ($subscribedProduct->payment_schedule != $order->payment_schedule) {
                            $lastPaidOrSkippedOrderByFrequency = $this->orders()
                                ->whereJsonContains('payment_schedule->frequency', data_get($subscribedProduct->payment_schedule,'frequency'))
                                ->whereIn('order_status_id', [
                                    OrderStatus::SKIPPED,
                                    OrderStatus::PAID,
                                ])
                                ->get()
                                ->last();

                            $lastPaidBillingDate= null;

                            if (!$lastPaidOrSkippedOrderByFrequency) {
                                $lastPaidOrder = $this->orders()
                                    ->whereIn('order_status_id', [
                                        OrderStatus::SKIPPED,
                                        OrderStatus::PAID,
                                    ])
                                    ->get()
                                    ->last();

                                if ($lastPaidOrder) {
                                    $lastPaidBillingDate = $lastPaidOrder->billing_date;

                                    $subscribedProduct->fill([
                                        'payment_schedule' => format_payment_schedule(
                                            $subscribedProduct->payment_schedule['frequency'],
                                            $lastPaidBillingDate
                                        )
                                        ]);
                                }
                            }

                            $billingDate = $lastPaidOrSkippedOrderByFrequency
                                ? $lastPaidOrSkippedOrderByFrequency->billing_date
                                : $lastPaidBillingDate;

                            $order->forceFill([
                                'payment_schedule' => $subscribedProduct->payment_schedule,
                                'billing_date' => collect(PaymentSchedule::getBillingCycle($subscribedProduct->payment_schedule, $billingDate))
                                    ->last(),
                            ])
                            ->saveQuietly();
                        }
                    });
                return $subscribedProduct->setTotalPrice();

            })
            ->groupBy('group_number')
            ->each(function (Collection $products, $groupNumber) use ($order){
                if ($groupNumber !== '') {
                    $unchangedProducts = $products
                        ->filter(function (SubscribedProduct $product) {
                            $newFrequency = $product->payment_schedule['frequency'];
                            $originalFrequency = data_get($product->getOriginal('payment_schedule'), 'frequency');

                            return !$product->isDirty('payment_schedule')
                                || $newFrequency == $originalFrequency;
                        });

                    if (
                        $unchangedProducts->isEmpty()
                        && ($products->count() === 1 || $products->unique('payment_schedule')->count() > 1)
                    ) {
                        $products->shift()->save();
                    }

                    $unchangedProducts->each->save();

                    $products = $products->filter(function (SubscribedProduct $product) {
                        return $product->isDirty('payment_schedule');
                    });

                }

                $allProductsWereChangedInFrequency = $order && $products->isNotEmpty()
                    ? empty(array_diff(
                            $order->products()->pluck('product_id')->toArray(),
                            $products->pluck('product_id')->toArray()
                        ))
                    : false;

                $products
                    ->groupBy(function (SubscribedProduct $product) {
                        return PaymentSchedule::toKeyString($product->payment_schedule);
                    })
                    ->values()
                    ->each(function (Collection $products) use ($allProductsWereChangedInFrequency, $groupNumber) {

                        if(!$allProductsWereChangedInFrequency) {
                            $groupNumber = $this->products()->max('group_number') + 1;
                        }

                            $products->each(function (SubscribedProduct $product) use ($groupNumber) {
                                $temporaryGroupNumber = $groupNumber;

                                if (
                                    !Arr::has($product, 'id')
                                    && $group = data_get($product, 'group_number')
                                ) {
                                    $temporaryGroupNumber = $group;
                                }

                                $product
                                    ->setAttribute('group_number', $temporaryGroupNumber)
                                    ->setTotalPrice()
                                    ->save();
                            });
                    });
            });

        return $this
            ->setTotalPrice()
            ->setMembership()
            ->syncOrderedProducts($order);
    }

    /**
     * Set membershipflag
     *
     * @return self
     */
    public function setMembership()
    {
        $isMemberShip = $this->products()
            ->get()
            ->contains(function (SubscribedProduct $product) {
                return $product->is_membership;
            });

        $this->forceFill([
            'is_membership' => $isMemberShip
        ])->saveQuietly();

        return $this;
    }

    /**
     * Sync the subscribed products.
     *
     * @param  array  $products
     * @return self
     */
    public function syncProducts($products)
    {
        $productIds = collect($products)->pluck('id')->filter()->all();
        $this->products()->whereKeyNot($productIds)->get()->each->delete();

        collect($products)
            ->each(function ($product) {
                $attributes = $product['attributes'];
                $attributes['total_price'] = data_get($attributes, 'price', 0)
                    ? data_get($attributes, 'price', 0) * data_get($attributes, 'quantity', 0)
                    : null;

                $this->products()->updateOrCreate(Arr::only($product, 'id'), $attributes);
            });

        return $this;
    }

    /**
     * Sync products for unpaid/future orders.
     *
     * @return self
     */
    public function syncOrderedProducts()
    {
        $initialOrder = $this->initialOrder()->first();
        $groupedProducts = $this->products()->get()->groupBy('group_number');
        $billingDate = now();

        if (!$initialOrder) {
            $this->createInitialOrders();

            $initialOrder = $this->initialOrder()->first();
        } elseif (!$initialOrder->isPaid()) {
            $initialOrder->syncProductsFromSubscription(
                $groupedProducts->flatten(1)
            );

            if ($groupedProducts->count() > 1) {
                $initialOrder->setAttribute('group_number', null)->saveQuietly();
            }

            $billingDate = $initialOrder->billing_date;
        }

        $hasSinglePayment = !is_null($this->max_payment_count)
            && $this->max_payment_count === 1;

        $groupedProducts
            ->each(function (Collection $products, $groupNumber) use (
                $initialOrder, $billingDate, $hasSinglePayment
            ) {
                $orders = $this->orders()
                    ->whereKeyNot($initialOrder->getKey())
                    ->where('group_number', $groupNumber)
                    ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::INCOMPLETE, OrderStatus::FAILED])
                    ->get();

                $schedule = $products->first()->payment_schedule;

                if ($schedule['frequency'] === 'single') {
                    $hasSinglePayment = true;
                }

                $lastPaidOrderByFrequency = $this->orders()
                    ->where('order_status_id', OrderStatus::PAID)
                    ->whereJsonContains('payment_schedule->frequency', $schedule['frequency'])
                    ->orderBy('id', 'DESC')
                    ->first();

                /*
                 * Copy the last paid order payment schedule
                */
                $schedule = $lastPaidOrderByFrequency
                    ? $lastPaidOrderByFrequency->payment_schedule
                    : $schedule;

                $lastBillingDateDiffersInFrquency =  false;
                if (!$lastPaidOrderByFrequency && $initialOrder->isPaid()) {
                    $billingDate = $this->lastPaidOrder?->billing_date;
                    $lastBillingDateDiffersInFrquency =  true;
                }

                if ($orders->isEmpty() && !$hasSinglePayment) {
                    $billingDate = $lastPaidOrderByFrequency
                        ? $lastPaidOrderByFrequency->billing_date
                        : $billingDate;

                    $date = Arr::last(
                        PaymentSchedule::getBillingCycle($schedule, $billingDate, $lastBillingDateDiffersInFrquency)
                    );

                    if (!$initialOrder->isPaid() && $initialOrder->order_status_id == OrderStatus::CANCELLED) {
                        $date = $initialOrder->billing_date;
                        $schedule = $initialOrder->payment_schedule;
                    }

                    return $this->createOrder([
                            'billing_date' => $date,
                            'payment_schedule' => $schedule,
                        ],
                        $groupNumber,
                        true
                    );
                }

                $orders->each(function (Order $order) use ($products) {
                    $uniqueSchedule = $products
                        ->pluck('payment_schedule')
                        ->unique(function ($schedule) {
                            return PaymentSchedule::toKeyString($schedule);
                        });

                    $order->payment_schedule = $uniqueSchedule->count() > 1
                        ? null
                        : $uniqueSchedule->first();

                    $order->syncProductsFromSubscription($products);
                });
            });
    }

    /**
     * Generate the next orders.
     *
     * @param  int|null  $groupNumber
     * @param  string|null  $action
     * @param  object|null  $order
     * @param  int|null  $originalPaymentType
     * @return void
     */
    public function generateNextOrders(
        $groupNumber = null,
        $action = null,
        $order = null,
        $originalPaymentType = null
    ) {
        $latestOrders = collect();

        if ($groupNumber) {
            $latestOrders = $this->orders()
                ->whereNotNull('group_number')
                ->where('order_status_id', '<>', OrderStatus::CANCELLED)
                ->where('group_number', $groupNumber)
                ->orderBy('billing_date', 'desc')
                ->take(1)
                ->get();
        } else {
            $maxGroupNumber = $this->products()->max('group_number');

            for ($i = 1; $i <= $maxGroupNumber; $i++) {
                $latestOrder = $this->orders()
                    ->whereNotNull('group_number')
                    ->where('group_number', $i)
                    ->orderBy('billing_date', 'desc')
                    ->first();

                if ($latestOrder) {
                    $latestOrders->push($latestOrder);
                }
            }
        }

        $isInitialOrderMixed = is_null($this->initialOrder()->pluck('group_number'));

        $latestOrders->each(function (Order $latestOrder) use (
            $isInitialOrderMixed, $action, $order, $originalPaymentType
        ) {
            if ($maxPaymentCount = $this->max_payment_count) {
                $orderCount = $this->orders()
                    ->where('order_status_id', '<>', OrderStatus::SKIPPED)
                    ->where(function ($query) use ($latestOrder) {
                        $query
                            ->whereNull('group_number')
                            ->orWhere('group_number', $latestOrder->group_number);
                    })
                    ->count();

                $orderCount += $isInitialOrderMixed ? 1 : 0;
                $hasRemainingOrders = ($maxPaymentCount - $orderCount) > 0;
            } else {
                $hasRemainingOrders = true;
            }

            if ($hasRemainingOrders && ($latestOrder->hasLapsed() || $latestOrder->isSatisfied())) {
                $orderReference = $order
                    && ($action == 'skipped'
                    || $action == 'create'
                    || $action == 'manually_paid')
                    ? $order
                    : $latestOrder;

                $nextBillingDate = Arr::last(
                    PaymentSchedule::getBillingCycle(
                        $orderReference->payment_schedule
                            ?: $orderReference->products
                                ->first()
                                ->payment_schedule,
                        $orderReference->billing_date
                    )
                );

                if ($action == 'manually_paid') {
                    $paymentType = $originalPaymentType == PaymentType::CASH
                        ? $this->orders()
                            ->whereNotNull('group_number')
                            ->where('order_status_id', '<>', OrderStatus::CANCELLED)
                            ->where('group_number', $orderReference->group_number)
                            ->whereKeyNot($orderReference->id)
                            ->where('payment_type_id', '!=', PaymentType::CASH)
                            ->orderBy('billing_date', 'desc')
                            ->take(1)
                            ->value('payment_type_id')
                        : $originalPaymentType;
                }

                $hasNextOrder = $this->orders()
                    ->where('group_number', $orderReference->group_number)
                    ->where('billing_date', $nextBillingDate)
                    ->exists();

                if (
                    $hasNextOrder
                    || ($action == 'create'
                        && $order->order_status_id == OrderStatus::UNPAID)
                ) {
                    return;
                }

                $newOrder = $this->orders()
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

                $this->products()
                    ->where('group_number', $orderReference->group_number)
                    ->get()
                    ->each(function (SubscribedProduct $product) use ($newOrder, $order) {
                        ($orderedProduct = $newOrder->products()->make($product->toArray()))
                            ->subscribedProduct()
                            ->associate($product);


                        $previousOrderedProduct = $order->products
                            ->where('product_id',$product->product_id)
                            ->first();


                        if ($maxOrderCount = $product->max_discounted_order_count) {
                            $orderCount = $this->orders()
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

                        $orderedProduct->setTotalPrice()->save();
                    });

                $this->cascadeVoucher($newOrder);

                $newOrder->setTotalPrice();
            }
        });
    }

    /**
     * Initialize payment for the current order.
     *
     * @return self
     */
    public function initializePayment()
    {
        $paymentTypeId = (int) $this->payment_type_id;
        $order = $this->initialOrder()->first();

        if (!$paymentTypeId) {
            return $this;
        }

        if (!optional($order)->total_price) {
            if ($paymentTypeId === PaymentType::CARD) {
                PayMaya::setVaultKeys(
                    $this->merchant->paymaya_vault_console_public_key
                        ?? $this->merchant->paymaya_vault_public_key,
                    $this->merchant->paymaya_vault_console_secret_key
                        ?? $this->merchant->paymaya_vault_secret_key
                );

                $this->customer->createPayMayaRecord($order);
                $this->linkCardToCustomer([
                    'subscription' => $this->getKey(),
                    'order' => $order->getKey(),
                    'subdomain' => $this->merchant->subdomain,
                    'action' => 'verify',
                ]);
            }

            if ($paymentTypeId === PaymentType::PAYMAYA_WALLET) {
                (new PaymentService)->linkPayMayaWallet($order)->save();
            }

            return $this;
        }

        if (
            $this->created_at->isSameDay($order->billing_date)
            || request()->isFromGuest()
            || request()->isFromCustomer()
        ) {
            $order->startPayment();
        }

        return $this;
    }

    /**
     * Notify customer that the subscription is confirmed
     *
     * @return void
     */
    public function notifyCustomer($type, $isCustomMerchant = false, $customOrder = null)
    {
        $customer = $this->customer()->first();
        $order = $this->initialOrder()->first();

        if ($customOrder) {
            $order = $customOrder;
        }

        if (!$order || !$customer) return;

        switch ($type) {
            case 'success':
                $customer->notify(
                    (new OrderPaid($order))->setChannel(['mail','sms','viber'])->afterCommit()
                );

                $initialOrder = $this->initialOrder()->first();

                if ($initialOrder?->id != $order->id) {
                    $this->merchant->notify(
                        (new OrderPaid($order))->setChannel('merchantviber')->afterCommit()
                    );
                }
                break;

            case 'failed':
                $customer->notify(
                    (new OrderFailed($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );

                $this->merchant->notify(
                    (new OrderFailed($order))->setChannel('merchantviber')->afterCommit()
                );
                break;

            case 'skipped':
                $customer->notify(
                    (new OrderSkipped($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );

                $this->merchant->notify(
                    (new OrderSkipped($order))->setChannel('merchantviber')->afterCommit()
                );
                break;

            case 'cancelled':
                $order =  $this->nextOrder;

                $customer->notify(
                    (new OrderCancelled($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );

                $this->merchant->notify(
                    (new OrderCancelled($order))->setChannel('merchantviber')->afterCommit()
                );
                break;

            case 'shipped':
                $customer->notify(
                    (new OrderShipped($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );
                break;

            case 'edit-confirmation':
                $customer->notify(
                    (new OrderUpdated($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );
                break;

            case 'payment':
                $customer->notify(
                    (new OrderPayment($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );
                break;

            default:
                $customer->notify(
                    (new OrderConfirmed($order))->setChannel(['mail', 'sms', 'viber'])->afterCommit()
                );
                break;
        }
    }

    /**
     * Notify customer that the subscription is confirmed
     *
     * @param  \App\Models\Customer  $customer
     * @param  string  $type
     * @param  \App\Models\Order|null  $order
     * @param  string  $time
     * @param  array|null  $options
     * @return void
     */
    public function messageCustomer($customer, $type, $order = null, $time = '', $options = null)
    {
        if (!$customer->mobile_number || $customer->country->name != 'Philippines') return;

        $subscriptionId = formatId($this->created_at, $this->id);

        $payNowText = 'Pay Now';

        $startOrContinue = start_or_continue($this, $order['id']);
        switch ($type) {
            case 'payment':
                $billingDate = Carbon::parse($order['billing_date'])->format('F d');

                $smsEditUrl = "\n{$payNowText}: ".$this->setType($time)
                    ->setEditUrl($order['id'], $this->id, $customer->id, $this->is_console_booking, true, true, 'sms');

                $viberEditUrl = "\n{$payNowText}: ".$this->setType($time)
                    ->setEditUrl($order['id'], $this->id, $customer->id, $this->is_console_booking, true, true, 'viber');

                switch ($time) {
                    case 'before':
                        $title = data_get($options, 'title')
                            ? replace_placeholders(data_get($options, 'title'), $order)
                            : "This is a gentle reminder that you need to pay on {$billingDate} to {$startOrContinue} your subscription with {$this->merchant->name}.";

                        $subtitle = data_get($options, 'subtitle')
                            ? replace_placeholders(data_get($options, 'subtitle'), $order)
                            : '';

                        $title .= "\n{$subtitle}\n";

                        $textInfo = "{$title}";
                        $viberTextInfo = $textInfo;

                        $footer = "\n\nID: {$subscriptionId}"
                            . "\n\n\nThank you,"
                            . "\n{$this->merchant->name}";

                        $textInfo = replace_placeholders($textInfo, $order);
                        $viberTextInfo = replace_placeholders($viberTextInfo, $order);

                        $body = "{$textInfo} {$smsEditUrl}&action=payNow {$footer}";
                        $viberBody = "{$viberTextInfo} {$viberEditUrl}&action=payNow {$footer}" ?? $body;
                        break;

                    case 'today':
                        $title = data_get($options, 'title')
                            ? replace_placeholders(data_get($options, 'title'), $order)
                            : 'Outstanding payment due!';

                        $subtitle = data_get($options, 'subtitle')
                            ? replace_placeholders(data_get($options, 'subtitle'), $order)
                            :  $this->merchant->due_payment_subheader_text
                                ?? "Please pay now to {$startOrContinue} your {$this->merchant->subscription_term_singular}.";

                        $textInfo = "{$title}"
                            . "\n{$subtitle}\n";
                        $viberTextInfo = $textInfo;

                        $footer = "\n\nID: {$subscriptionId}"
                            . "\n\n\nThank you,"
                            . "\n{$this->merchant->name}";

                        $textInfo = replace_placeholders($textInfo, $order);
                        $viberTextInfo = replace_placeholders($viberTextInfo, $order);

                        $body = "{$textInfo} {$smsEditUrl}&action=payNow  {$footer}";
                        $viberBody = "{$viberTextInfo} {$viberEditUrl}&action=payNow {$footer}" ?? $body;
                        break;

                    case 'after':
                        $title = data_get($options, 'title')
                            ? replace_placeholders(data_get($options, 'title'), $order)
                            : 'Outstanding payment due!';

                        $subtitle = data_get($options, 'subtitle')
                            ? replace_placeholders(data_get($options, 'subtitle'), $order)
                            :  $this->merchant->due_payment_subheader_text
                                ?? "Please pay now to {$startOrContinue} your {$this->merchant->subscription_term_singular}.";

                        $textInfo = "{$title}"
                            . "\n{$subtitle}";
                        $viberTextInfo = $textInfo;

                        $footer = "\n\nID: {$subscriptionId}"
                            . "\n\n\nThank you,"
                            . "\n{$this->merchant->name}";

                        $textInfo = replace_placeholders($textInfo, $order);
                        $viberTextInfo = replace_placeholders($viberTextInfo, $order);

                        $body = "{$textInfo} {$smsEditUrl}&action=payNow  {$footer}";
                        $viberBody = "{$viberTextInfo} {$viberEditUrl}&action=payNow {$footer}" ?? $body;
                        break;

                    case 'edit':
                        $body = "This is a notification for you to update your {$this->merchant->subscription_term_singular} with {$this->merchant->name}."
                            . "\nEdit Link: {$this->editUrl}"
                            . "\n\nID: {$subscriptionId}"
                            . "\n\n\nThank you,"
                            . "\n{$this->merchant->name}";
                        $viberBody = $body;
                        break;


                default:
                    $header = "This is a gentle reminder that you need to pay today to {$startOrContinue} your {$this->merchant->subscription_term_singular} with {$this->merchant->name}.";

                    if ($this->merchant->console_created_email_headline_text) {
                        $subheader = $this->merchant->console_created_email_subheader_text
                            ?? "Please enter your payment details to activate your {$this->merchant->subscription_term_singular}.";

                        $header = $this->merchant->console_created_email_headline_text
                            . "\n{$subheader}";
                    }

                    $body = replace_placeholders($header, $order)
                        . "\n{$payNowText}: {$this->editUrl}"
                        . "\n\nID: {$subscriptionId},"
                        . "\n\n\nThank you,"
                        . "\n{$this->merchant->name}";
                    $viberBody = $body;

                }
                break;
        }

        $customer->notify(new DynamicSmsNotification($body));

        if ($customer->viber_info) {
            if ($customer->merchant->viber_key) {
                return Viber::withToken(
                    $customer->merchant->viber_key,
                    function () use($customer, $viberBody) {
                        return ViberMessage::send(
                            $customer->viber_info['id'],
                            $viberBody
                        );
                    }
                );
            }

            ViberMessage::send(
                $customer->viber_info['id'],
                $viberBody
            );
        }
    }

    /**
     * Link the wallet to the customer.
     *
     * @param  array  $parameters
     * @return self
     */
    public function linkWalletToCustomer($order) {
        (new Wallet(formatId($order->created_at, $order->id)))
            ->link([
            'smi' => smi($order->paymaya_card_type),
            'smn' => mb_substr(
                preg_replace('/\s/', '', $this->merchant->name), 0, 9
            ),
        ], [
            'merchant' => $this->merchant_id,
            'subscription' => $this->id,
            'order' => $order->getKey(),
        ])
        ->then(function ($payment) use($order) {
            $this->forceFill([
                'paymaya_link_id' => data_get($payment, 'linkId')
            ])->update();

            $order->forceFill([
                'payment_info' => $payment,
                'payment_url' => $payment['redirectUrl'],
                'paymaya_link_id' => data_get($payment, 'linkId'),
            ])->update();
        }, function ($e) {
            $this->forceFill([
                'payment_status_id' => PaymentStatus::FAILED,
                'payment_info' => [
                    'error' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                        'body' => json_decode($e->getResponse()->getBody(), true),
                    ],
                ],
            ]);
        })
        ->wait(false);

        return $this;
    }

    /**
     * Link the card to the customer.
     *
     * @param  array  $parameters
     * @return self
     */
    public function linkCardToCustomer($parameters = [])
    {
        if (!$this->paymaya_payment_token_id) {
            return $this;
        }

        $smi = smi($this->paymaya_card_type);
        $smn = mb_substr(
            preg_replace('/\s/', '', $this->merchant()->value('name')), 0, 9
        );

        Card::link(
            $this->customer->paymaya_uuid,
            $this->paymaya_payment_token_id,
            true,
            compact('smi', 'smn'),
            $parameters
        )->then(function ($card) {
            $this->forceFill([
                'paymaya_payment_token_id' => null,
                'paymaya_verification_url' => $card['verificationUrl'],
                'paymaya_card_token_id' => $card['cardTokenId'],
                'paymaya_card_type' => $card['cardType'],
                'paymaya_masked_pan' => $card['maskedPan'],
            ])->update();

            $this->customer->cards()
                ->firstOrNew([
                    'card_token_id' => data_get($card, 'cardTokenId'),
                ], [
                    'card_type' => data_get($card, 'cardType'),
                    'masked_pan' => data_get($card, 'maskedPan'),
                ])
                ->touch();
        }, function ($e) {
            throw $e;
        })->wait();

        return $this;
    }

    /**
     * cascade voucher on orders.
     *
     * @param  array||object  $order
     */
    public function cascadeVoucher($order)
    {
        if ($this->voucher) {
            $usedVoucherCount = $this->customer
                ->usedVouchers()
                ->where('id', $this->voucher_id)
                ->count();

            $usageCount = $this->voucher->applicable_order_count ?? PHP_INT_MAX;

            if ($this->voucher->remaining_count && $usedVoucherCount < $usageCount) {
                $this->voucher->use($order, throwError: false);
            }
        }
    }


    /**
     * Send new subscriber email notification to merchants.
     *
     * @return void
     */
    public function sendNewSubscriberNotification()
    {
        $merchant = $this->merchant;

        $merchant->users()
            ->get()
            ->each(function (MerchantUser $user) use ($merchant) {
                if (
                    !$user->email
                    || !$merchant->is_new_subscriber_email_enabled
                    || !$user->is_enabled
                    || !$user->email_verified_at
                ) return;

                $user->notify((new NewSubscriberEmailNotification($merchant, $this)));
            });


        $products = formatProducts($this->products()->get());

        $merchant->sendViberNotification(
            "You have a new subscriber {$this->customer->name} for {$products}."
        );
    }

}
