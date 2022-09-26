<?php

namespace App\Models;

use Ankurk91\Eloquent\Relations\BelongsToOne;
use App\Exceptions\BadRequestException;
use App\Facades\PayMaya;
use App\Libraries\Brankas\Direct;
use App\Libraries\PayMaya\Customer;
use App\Libraries\PayMaya\Response;
use App\Notifications\PaymentReminder;
use App\Services\PaymentService;
use App\Support\ConvenienceFee;
use App\Support\PaymentSchedule;
use App\Support\ShippingFee;
use App\Traits\LogsPaymentInfoChanges;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class Order extends RecordableModel
{
    use HasFactory, LogsPaymentInfoChanges, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscription_id',
        'payment_type_id',
        'bank_id',
        'payment_status_id',
        'order_status_id',
        'shipping_method_id',
        'transaction_id',
        'shopify_order_id',

        'original_price',
        'total_price',
        'shipping_fee',
        'custom_shipping_fee',
        'bank_fee',
        'vat_amount',
        'convenience_fee',

        'payor',
        'billing_date',
        'billing_address',
        'billing_city',
        'billing_province',
        'billing_barangay',
        'billing_zip_code',
        'billing_country',

        'recipient',
        'shipping_date',
        'shipping_address',
        'shipping_city',
        'shipping_province',
        'shipping_barangay',
        'shipping_zip_code',
        'shipping_country',

        'fulfillment_date',

        'paymaya_card_token_id',
        'paymaya_card_type',
        'paymaya_masked_pan',

        'order_number',

        'paid_at',
        'shipped_at',
        'fulfilled_at',

        'payment_schedule',

        'is_auto_charge',
        'previous_balance'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'payment_info' => 'array',
        'payment_schedule' => 'array',
        'info' => 'array',
        'billing_date' => 'date',
        'shipping_date' => 'date',
        'fulfillment_date' => 'date',
        'has_payment_lapsed' => 'boolean',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'is_auto_charge' => 'boolean',
        'ignores_inventory' => 'boolean',
    ];

    /**
     * Set the payment schedule attribute.
     *
     * @param  array|null  $value
     * @return self
     */
    public function setPaymentScheduleAttribute($value)
    {
        if (is_array($value)) {
            ksort($value);
        }

        if (Arr::has($value, 'day')) {
            $value['day'] = (int) $value['day'];
        }

        if (Arr::has($value, 'day_of_week')) {
            $value['day_of_week'] = (int) $value['day_of_week'];
        }

        if (Arr::has($value, 'value')) {
            $value['value'] = (int) $value['value'];
        }

        $this->attributes['payment_schedule'] = $value ? json_encode($value) : $value;

        return $this;
    }

    /**
     * A service provider belongs to many categories.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function attachments(): BelongsToMany
    {
        return $this
            ->belongsToMany(Attachment::class, OrderAttachment::class)
            ->withTimestamps();
    }

    /**
     * Get the next order with the same frequency.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function nextOrder(): HasOne
    {
        return $this
            ->hasOne(self::class, 'subscription_id', 'subscription_id')
            ->whereIn('order_status_id',[
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED
            ])
            ->ofMany(['billing_date' => 'max'], function ($query) {
                $query
                    ->where('payment_schedule->frequency', data_get($this->payment_schedule, 'frequency'))
                    ->where('id', '<>', $this->id);
            });
    }

    /**
     * Get the transaction logs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attemptLogs(): HasMany
    {
        return $this->hasMany(PaymentAttemptLog::class)
            ->latest('payment_attempt_logs.id');
    }

    /**
     * Get the order status.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class);
    }

    /**
     * Get the payment initiator.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function paymentInitiator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the payment status of the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentStatus(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class);
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
     * Get the subscribed products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(OrderedProduct::class);
    }

    /**
     * Get the voucher used on this job.
     *
     * @return \Ankurk91\Eloquent\Relations\BelongsToOne
     */
    public function voucher(): BelongsToOne
    {
        return $this->belongsToOne(Voucher::class, UsedVoucher::class)->withTrashed();
    }

    /**
     * Get the shipping method for the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get the status of the order.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    /**
     * Get the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
     * Get the related overdue's orders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function overdueOrders(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'order_overdues',
            'order_id',
            'overdue_order_id'
        );
    }

    /**
     * Scope a query to only include orders for cancellation.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCancellation($query)
    {
        return $query
            ->whereIn('order_status_id', [
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED,
            ])
            ->whereNotNull('total_price')
            ->whereHas('subscription.merchant', function ($query) {
                $query->where(function ($query) {
                    $query
                        ->whereNotNull('merchants.auto_cancellation_days')
                        ->whereRaw("orders.billing_date <= DATE_SUB(
                            DATE(CONVERT_TZ(NOW(), 'SYSTEM', ?)),
                            INTERVAL merchants.auto_cancellation_days DAY
                        )", [config('app.timezone')]);
                })->orWhere(function ($query) {
                    $query
                        ->whereNull('merchants.auto_cancellation_days')
                        ->whereRaw("orders.billing_date <= DATE_SUB(
                            DATE(CONVERT_TZ(NOW(), 'SYSTEM', ?)),
                            INTERVAL ? DAY
                        )", [config('app.timezone'), setting('AutoCancellationDays', 95)]);
                });
            });
    }

    /**
     * Scope a query to only include orders that has lapsed payments.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLapsed($query)
    {
        $timestamp = now()
            ->subMinutes(2);

        return $query
            ->where('payment_attempted_at', '<', $timestamp->toDateTimeString())
            ->whereDate('payment_attempted_at', '>=', $timestamp->toDateString())
            ->where(function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->where('payment_status_id', PaymentStatus::PENDING)
                            ->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::INCOMPLETE]);
                    })
                    ->orWhere(function ($query) {
                        $query
                            ->where('payment_status_id', PaymentStatus::INCOMPLETE)
                            ->whereNotNull('payment_initiator_id')
                            ->whereNotNull('payment_initiator_type')
                            ->where('has_payment_lapsed', false);
                    });
            });
    }

    /**
     * Scope a query to only include payable orders.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePayable($query)
    {
        return $query->whereIn('order_status_id', [
            OrderStatus::UNPAID,
            OrderStatus::INCOMPLETE,
            OrderStatus::FAILED,
        ]);
    }

    /**
     * Scope a query to only include/exclude satisfied (paid or skipped) orders.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  bool  $include
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSatisfied($query, $include = true)
    {
        return $query
            ->when($include, function ($query) {
                $query->whereIn('order_status_id', [
                    OrderStatus::PAID,
                    OrderStatus::SKIPPED,
                ]);
            }, function ($query) {
                $query->whereNotIn('order_status_id', [
                    OrderStatus::PAID,
                    OrderStatus::SKIPPED,
                ]);
            });
    }

    /**
     * Scope a query to only include those that are paid and not shipped orders.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUpcomingTransactions($query)
    {
        return $query
            ->where(function ($query) {
                return $query
                    ->where('order_status_id', OrderStatus::PAID)
                    ->whereNull('shipped_at');
            });

    }

    /**
     * Get the appropriate response for the payment error.
     *
     * @return array
     */
    public function getErrorResponse()
    {
        $default = [
            'title' => 'Payment Unsuccessful!',
            'subtitle' => Str::makeReplacements(
                'Please change your payment method to {startContinue} with your {subscription}.',
                $this
            ),
        ];

        /** @var \App\Models\PaymentType */
        if (!$paymentType = $this->paymentType()->first()) {
            return $default;
        }

        switch ($paymentType->getKey()) {
            case PaymentType::GCASH:
            case PaymentType::GRABPAY:
                $errorCode = data_get(
                    $this->payment_info,
                    'error.body.code',
                    data_get($this->payment_info, 'charge.failure_code')
                );
                break;

            case PaymentType::CARD:
                $default['title'] = 'Your Card Failed!';

            case PaymentType::PAYMAYA_WALLET:
                $errorCode = data_get(
                    $this->payment_info,
                    'error.body.code',
                    data_get($this->payment_info, 'payment.errorCode')
                );
                break;

            case PaymentType::BANK_TRANSFER:
                $errorCode = data_get($this->payment_info, 'transaction.status_code');
                break;

            default:
                $errorCode = null;
        }

        if (is_null($errorCode)) {
            $errorCode = $default;
        }

        /** @var \App\Models\PaymentErrorResponse|null */
        $response = $paymentType->errorResponses()
            ->when($errorCode, function ($query) use ($errorCode) {
                $query
                    ->where(function ($query) use ($errorCode) {
                        $query
                            ->where('is_enabled', true)
                            ->whereJsonContains('error_codes', $errorCode);
                    })
                    ->orWhere('is_default', true);
            })
            ->orderBy('is_default')
            ->orderBy('id')
            ->first();

        if (!$response) {
            $response = $default;
        }

        return [
            'title' => Str::makeReplacements(data_get($response, 'title'), $this),
            'subtitle' => Str::makeReplacements(data_get($response, 'subtitle'), $this),
        ];
    }

    /**
     * Get the URL for editing the order.
     *
     * @param  bool  $success
     * @param  bool  $forPayment
     * @param  string  $fromWhere
     * @return string
     */
    public function getEditUrl($success, $forPayment = true, $fromWhere = 'email')
    {
        $merchant = $this->subscription->merchant;
        $url = $this->subscription->is_console_booking
            ? config('bukopay.url.subscription_checkout')
            : config('bukopay.url.edit');

        $query = [
            'ord' => Hashids::connection('order')->encode($this->getKey()),
            'sub' => Hashids::connection('subscription')->encode($this->subscription_id),
            'cust' => Hashids::connection('customer')->encode($this->subscription->customer_id),
            'success' => $success,
            'isPayment' => $forPayment,
            'isConsoleBooking' => $this->subscription->is_console_booking,
        ];

        if ($fromWhere) {
            $query['isFrom' . Str::ucfirst($fromWhere)] = true;
        }

        return "https://{$merchant->subdomain}.{$url}?" . http_build_query($query);
    }

    /**
     * Get the label for payment buttons.
     *
     * @return string
     */
    public function getPaymentLabel()
    {
        return $this->isInitial()
            ? $this->subscription->merchant->pay_button_text ?? 'Pay Now'
            : $this->subscription->merchant->recurring_button_text ?? 'Start Subscription';
    }

    /**
     * Check if the order has digital products.
     *
     * @return bool
     */
    public function hasDigitalProducts()
    {
        return $this->products()->where('is_shippable', false)->exists();
    }

    /**
     * Check if the billing date has lapsed.
     *
     * @return bool
     */
    public function hasLapsed()
    {
        return now()->startOfDay()->gte($this->billing_date);
    }

    /**
     * Check if the payment has lapsed.
     *
     * @return bool
     */
    public function hasPaymentLapsed()
    {
        return $this->order_status_id == OrderStatus::INCOMPLETE
            && $this->has_payment_lapsed;
    }

    /**
     * Check if the order has a payable status.
     *
     * @return bool
     */
    public function hasPayableStatus()
    {
        $orderStatusId = (int) $this->order_status_id;

        if (in_array($orderStatusId, [OrderStatus::UNPAID ,OrderStatus::FAILED, OrderStatus::INCOMPLETE])) {
            return true;
        }

        return $orderStatusId === OrderStatus::CANCELLED
            && (now()->startOfDay()->diffInDays($this->billing_date) <= 30);
    }

    /**
     * Check if the order has shippable products.
     *
     * @return bool
     */
    public function hasShippableProducts()
    {
        return $this->products()->where('is_shippable', true)->exists();
    }

    /**
     * Check if the billing date has lapsed.
     *
     * @return bool
     */
    public function hasOnlyOneProductWithSingleRecurrence()
    {
        $paymentSchedule = $this->products()->first()->payment_schedule;
        return $this->products()->count() === 1
            && $paymentSchedule
                ? $paymentSchedule['frequency'] === 'single'
                : false;
    }

    /**
     * Check if the order is the initial order for the subscription.
     *
     * @return bool
     */
    public function isInitial()
    {
        if (!$initialOrder = $this->subscription->initialOrder()->first()) {
            return false;
        }

        return $this->is($initialOrder);
    }

    /**
     * Check if the order is paid.
     *
     * @return bool
     */
    public function isPaid()
    {
        return !is_null($this->paid_at);
    }

    /**
     * Check if the order is payable.
     *
     * @return bool
     */
    public function isPayable()
    {
        $isBillingDateLate = now()->diffInDays(
            Carbon::parse($this->billing_date)->startOfDay()
        ) <= 30;

        $hasPayableStatus = in_array((int) $this->order_status_id, [
            OrderStatus::UNPAID,
            OrderStatus::INCOMPLETE,
            OrderStatus::FAILED,
        ]) || ($this->order_status_id == OrderStatus::CANCELLED && $isBillingDateLate);

        return $this->total_price && $hasPayableStatus;
    }

    /**
     * Check if the order sends reminders to the customer.
     *
     * @return bool
     */
    public function isRemindable()
    {
        return in_array((int) $this->payment_type_id, [
            PaymentType::GCASH,
            PaymentType::GRABPAY,
            PaymentType::BANK_TRANSFER,
        ]);
    }

    /**
     * Check if the order is satisfied (paid or skipped).
     *
     * @return bool
     */
    public function isSatisfied()
    {
        return in_array((int) $this->order_status_id, [
            OrderStatus::PAID,
            OrderStatus::SKIPPED,
        ]);
    }

    /**
     * Check if the order is shipped.
     *
     * @return bool
     */
    public function isShipped()
    {
        return !is_null($this->shipped_at);
    }

    /**
     * Check if the order consists of single order products.
     *
     * @return bool
     */
    public function isSingle()
    {
        return $this->products()->get()
            ->every(function ($product) {
                return data_get($product, 'payment_schedule.frequency') === 'single';
            });
    }

    /**
     * Check if the order is not notifiable.
     *
     * @param App\Models\OrderNotification $notification
     *
     * @return bool
     */
    public function isNotNotifiable($notification)
    {
        $hasPaidOrder = $this->subscription->orders()
            ->where('order_status_id', OrderStatus::PAID)
            ->count();

        if (
            $notification->purchase_type == OrderNotification::PURCHASE_SINGLE
            && !$this->isSingle()
        ) {
            return true;
        }

        if (
            $notification->purchase_type == OrderNotification::PURCHASE_SUBSCRIPTION
            && $this->isSingle()
        ) {
            return true;
        }

        if (
            $notification->subscription_type == OrderNotification::SUBSCRIPTION_AUTO_CHARGE
            && !$this->is_auto_charge
        ) {
            return true;
        }

        if (
            $notification->subscription_type == OrderNotification::SUBSCRIPTION_AUTO_REMIND
            && $this->is_auto_charge
        ) {
            return true;
        }

        if (
            $notification->applicable_orders == OrderNotification::ORDER_FIRST
            && !$this->isInitial()
        ) {
            return true;
        }

        if (
            $notification->applicable_orders == OrderNotification::ORDER_SUCCEEDING
            && $this->isInitial()
        ) {
            return true;
        }

        if (!$this->isInitial() && !$hasPaidOrder) {
            return true;
        }

        return false;
    }

    /**
     * Obfuscate the order's key.
     *
     * @return string
     */
    public function obfuscateKey()
    {
        return Carbon::parse($this->created_at)->format('ys')
            . str_pad((string) $this->getKey(), 6, '0', STR_PAD_LEFT);
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
                'customer' => $this->subscription->customer,
                'order' => $this
            ]);

            $totalDiscount += data_get($discount, 'discount_amount');
        }

        if (
            $this->payment_type_id == PaymentType::CARD
            && ($cardDiscount = $this->subscription->merchant->card_discount)
        ) {
            $originalPrice = $subtotal + $shippingFee;
            $totalDiscount += round($originalPrice * ($cardDiscount / 100), 2);
        }

        return $totalDiscount;
    }


    /**
     * Set the total price based on the ordered products and shipping method.
     *
     * @return self
     */
    public function setTotalPrice()
    {
        if (
            $this->payment_status_id == PaymentStatus::PAID
            && $this->payment_type_id !== PaymentType::CASH
        ) {
            return $this;
        }

        $subtotal = $this->products()->sum('total_price') ?: 0;

        if ($this->wasChanged('shipping_method_id')) {
            $shippingFee = ShippingFee::compute($this->shipping_method_id, $this->products()->get());
        } elseif ($this->custom_shipping_fee !== null) {
            $shippingFee = $this->custom_shipping_fee;
        } else {
            $shippingFee = $this->shipping_fee
                ?: ShippingFee::compute($this->shipping_method_id, $this->products()->get())
                ?: 0;
        }

        if (
            $subtotal == 0 ||
            ($this->subscription->merchant->free_delivery_threshold
            && $subtotal >= $this->subscription->merchant->free_delivery_threshold)
            || ($this->subscription->is_shopify_booking && $this->estimateDiscount() >= $subtotal)
            || ($this->products->isNotEmpty() && !are_shippable_products($this->products))
        ) {
            $shippingFee = 0;
        }

        $totalPrice = $subtotal + $shippingFee;

        if ($voucher = $this->voucher()->first()) {
            $originalPrice = $totalPrice;

            $discount = $voucher->computeTotalDiscount([
                'totalPrice' => $totalPrice,
                'products' => $this->products()->get(),
                'customer' => $this->subscription->customer,
                'order' => $this
            ]);

            $totalPrice -= data_get($discount, 'discount_amount');
        }

        if (
            $this->payment_type_id == PaymentType::CARD
            && $this->is_auto_charge
            && ($cardDiscount = $this->subscription->merchant->card_discount)
        ) {
            $originalPrice = $subtotal + $shippingFee;
            $totalPrice -= round($originalPrice * ($cardDiscount / 100), 2);
        }

        if (
            $this->payment_type_id == PaymentType::BANK_TRANSFER && $this->bank
            || $this->payment_type_id != PaymentType::BANK_TRANSFER
        ) {
            $convenienceDetails = ConvenienceFee::getConvenienceFee(
                $this->subscription->merchant,
                $this->payment_type_id,
                optional($this->bank)->code ?? null
            );
        }

        if (
            isset($convenienceDetails)
            && $totalPrice > 0
            && ($convenienceFee = $convenienceDetails['convenience_fee'])
        ) {
            if ($convenienceDetails['convenience_type_id'] == ConvenienceType::PERCENTAGE) {
                $convenienceFee = $totalPrice * ($convenienceFee / 100);
            }

            if (isset($originalPrice)) {
                $originalPrice += $convenienceFee;
            }

            $totalPrice += $convenienceFee;
        }

        if (
            $totalPrice > 0
            && $this->subscription->merchant->is_vat_enabled
        ) {
            $vatAmount = $totalPrice * 0.12;

            if (isset($originalPrice)) {
                $originalPrice += $vatAmount;
            }

            $totalPrice += $vatAmount;
        }

        if ($this->subscription->merchant->is_outstanding_balance_enabled) {
            $totalPrice += $this->previous_balance;
        }

        if (is_numeric($totalPrice)) {
            $totalPrice = max($totalPrice, 0);
        }

        if (
            $this->wasChanged('payment_schedule')
            && $this->payment_schedule
        ) {
            $lastPaidOrSkippedOrder = $this->subscription
                ->recentOrders()
                ->whereJsonContains('payment_schedule->frequency', data_get($this->payment_schedule,'frequency'))
                ->whereIn('order_status_id', [
                    OrderStatus::PAID,
                    OrderStatus::SKIPPED,
                ])
                ->get()
                ->last();

            $billingDate = $lastPaidOrSkippedOrder
                ? $lastPaidOrSkippedOrder->billing_date
                : null;

            $this->forceFill([
                'billing_date' => collect(PaymentSchedule::getBillingCycle($this->payment_schedule, $billingDate))
                    ->last(),
            ])->saveQuietly();
        }

        $this->forceFill([
            'bank_fee' => optional($this->bank)->fee ?: null,
            'shipping_fee' => $shippingFee ?: null,
            'original_price' => ($originalPrice ?? null) ?: null,
            'total_price' => round($totalPrice, 2) ?: null,
            'vat_amount' => ($vatAmount ?? null) ?: null,
            'convenience_fee' => ($convenienceFee ?? null) ?: null,
        ])->saveQuietly();

        return $this;
    }

    /**
     * Sync Products
     *
     * @return void
     */
    public function syncProducts($model, $products = [], $isShopifyBooking = false)
    {
        $productIds = collect($products)->pluck('attributes.id')->all();

        if ($isShopifyBooking) {
            $model->products()->get()->each->delete();
        } else {
            $model->products()->whereNotIn('product_id', $productIds)->get()->each->delete();
        }

        $products = collect($products)
            ->pluck('attributes')
            ->map(function ($product) {
                $product['total_price'] = data_get($product, 'price', 0)
                    ? data_get($product, 'price', 0) * data_get($product, 'quantity', 0)
                    : null;

                return $product;
            });

        foreach ($products as $product) {
            $model->products()->updateOrCreate(
                ['product_id' => $product['id']],
                $product
            );
        }
    }

    /**
     * Sync ordered products.
     *
     * @param  array  $products.
     * @return self
     */
    public function syncOrderedProducts($products)
    {
        $productIds = collect($products)->pluck('id')->filter()->all();
        $this->products()->whereKeyNot($productIds)->get()->each->delete();

        collect($products)
            ->each(function ($product) {
                $this->products()->findOrNew(data_get($product, 'id'))
                    ->fill(data_get($product, 'attributes', []))
                    ->setTotalPrice()
                    ->save();
            });

        return $this->setTotalPrice();
    }

    /**
     * Sync ordered products from subsription.
     *
     * @param  \Illuminate\Support\Collection  $products
     * @return self
     */
    public function syncProductsFromSubscription($products)
    {
        $this->products()->get()->each->delete();

        $products->each(function (SubscribedProduct $product) {
            $this->products()
                ->make($product->toArray())
                ->subscribedProduct()
                ->associate($product)
                ->save();
        });

        return $this->setTotalPrice();
    }

    /**
     * Update Future orders
     *
     * @return void
     */
    public function updateFutureOrders($orderDetails, $products = [])
    {
        $nextOrder = $this->subscription->nextOrder()->first();
        $merchant = $this->subscription->merchant()->first();

        $nextOrder->fill($orderDetails)->save();

        if (
            (!$this->total_price
            && $merchant->pricing_type_id == 2)
            || $merchant-> pricing_type_id == 1
        ) {
            $nextOrder->syncProducts($this, $products);
        }

        if ($merchant->pricing_type_id == 1) {
            $nextOrder->setTotalPrice();
        }
    }

    /**
     * Update Subscription
     *
     * @return void
     */
    public function updateSubscription(
        $subscription = [],
        $customerDetails = [],
        $products = []
    ) {
        $merchant = $this->subscription->merchant()->first();

        $this->subscription->forceFill($subscription)->update();

        if (!is_null($products)) {
            if (
                (!$this->total_price
                && $merchant->pricing_type_id == 2)
                || $merchant-> pricing_type_id == 1
            ) {
                $this->syncProducts($this->subscription, $products);
            }
        }

        if (Arr::has($subscription, 'paymaya_payment_token_id')) {
            $this->subscription->linkCardToCustomer([
                'subscription' => $this->subscription->id,
                'order' => $this->id,
                'subdomain' => $this->subscription->merchant->subdomain,
            ]);
        }

        if ($merchant->pricing_type_id == 1) {
            $this->subscription->setTotalPrice();
        }

        $this->subscription->customer->fill($customerDetails)->save();
    }

    /**
     * Start the payment process.
     *
     * @return $this
     */
    public function startPayment()
    {
        $paymentTypeId = (int) $this->payment_type_id;

        if (
            in_array($paymentTypeId, [PaymentType::GCASH, PaymentType::GRABPAY])
            && $this->subscription->merchant->isXenditLive()
        ) {
            return (new PaymentService)->start($this);
        }

        if (in_array($paymentTypeId, [PaymentType::CARD, PaymentType::PAYMAYA_WALLET])) {
            return (new PaymentService)->start($this);
        }

        if (!$this->total_price && $this->order_status_id != OrderStatus::PAID) {
            $this->paymentStatus()->associate(PaymentStatus::PAID)->save();

            return $this;
        }

        $isFromFailedPayment = $this->payment_status_id == PaymentStatus::FAILED;

        if (!$this->isPayable()) {
            return $this;
        }

        $this
            ->forceFill([
                'order_status_id' => OrderStatus::UNPAID,
                'payment_status_id' => PaymentStatus::PENDING,
                'payment_attempts' => ($this->payment_attempts ?: 0) + 1,
                'payment_attempted_at' => now()->toDateTimeString(),
                'has_payment_lapsed' => false,
            ])
            ->syncOriginalAttribute('order_status_id')
            ->paymentInitiator()
            ->associate(request()->userOrClient() ?: $this->subscription->customer);

        if (!Str::startsWith(request()->userAgent() ?: '', 'Mozilla/')) {
            $this
                ->forceFill([
                    'auto_payment_attempts' => ($this->auto_payment_attempts ?: 0) + 1,
                ])
                ->paymentInitiator()
                ->dissociate();
        }

        switch ($this->payment_type_id) {
            case PaymentType::GCASH:
            case PaymentType::GRABPAY:
                $this->forceFill([
                    'payment_info' => null,
                    'payment_url' => URL::signedUrl(
                        config('bukopay.url.checkout') . '/pesopay/' . encrypt($this->getKey())
                    ),
                ]);
                break;

            case PaymentType::BANK_TRANSFER:
                Direct::checkout($this->total_price, formatId($this->created_at, $this->id), [
                    'customer' => $this->subscription->customer,
                    'order' => $this->getKey(),
                    'isFromFailedPayment' => $isFromFailedPayment,
                    'merchantName' => $this->subscription->merchant->name,
                    'logo_url' => $this->subscription->merchant->logo_svg_path
                        ?? Storage::url('images/brankas/helixpay_white.svg'),
                    'bankCode' => $this->bank->code ?? null,
                ])->then(function ($payment) {
                    $this->forceFill([
                        'payment_info' => $payment,
                        'payment_url' => $payment['redirect_uri'],
                        'transaction_id' => data_get($payment, 'transaction_id')
                    ]);
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
                break;

            case PaymentType::CARD:
            default:
                $subscription = $this->subscription()->first();

                PayMaya::setVaultKeys(
                    $subscription->merchant->paymaya_vault_console_public_key
                        ?? $subscription->merchant->paymaya_vault_public_key,
                    $subscription->merchant->paymaya_vault_console_secret_key
                        ?? $subscription->merchant->paymaya_vault_secret_key
                );

                $subscription->customer->createPayMayaRecord($this);
                $subscription->linkCardToCustomer([
                    'subscription' => $subscription->getKey(),
                    'order' => $this->getKey(),
                    'subdomain' => $subscription->merchant->subdomain,
                    'action' => 'verify',
                ]);

                $this->forceFill($subscription->only([
                    'paymaya_card_token_id',
                    'paymaya_card_type',
                    'paymaya_masked_pan',
                ]));

                (clone $this)->update();

                (new Customer($subscription->customer->paymaya_uuid))
                    ->payWithCard($this->paymaya_card_token_id, $this->total_price, [
                        'smi' => smi($this->paymaya_card_type),
                        'smn' => mb_substr(
                            preg_replace('/\s/', '', $subscription->merchant->name), 0, 9
                        ),
                    ], [
                        'merchant' => $subscription->merchant_id,
                        'subscription' => $this->subscription_id,
                        'order' => $this->getKey(),
                        'isFromFailedPayment' => $isFromFailedPayment,
                    ])
                    ->then(function ($payment) {
                        $this->payment_info = compact('payment');

                        switch ($payment['status']) {
                            case Response::PAYMENT_SUCCESS:
                                $this->payment_status_id = PaymentStatus::PAID;
                                break;

                            case Response::FOR_AUTHENTICATION:
                                $this->payment_url = $payment['verificationUrl'];
                                break;

                            case Response::PAYMENT_FAILED:
                            case Response::PAYMENT_EXPIRED:
                                $this->payment_status_id = PaymentStatus::FAILED;
                                break;

                            default:
                                //
                        }
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
        }

        $this->update();

        return $this;
    }

    /**
     * Set the reminder options
     *
     * @param  \App\Models\Order  $order
     * @param  string  $type
     * @param  string|int|null  $orderNotificationId
     * @param  boolean  $hasOrderSummary
     *
     * @return array
     */
    public function setReminderOptions(
        $type,
        $hasOrderSummary,
        $orderNotificationId = null,
    ) {
        switch ($type) {
            case 'after':
                $daysFromBillingDate = 3;
                break;
            case 'before':
                $daysFromBillingDate = -3;
                break;
            case 'today':
            default:
                $daysFromBillingDate = 0;
                break;
        }

        $notification = $this->subscription->merchant->orderNotifications()
            ->where('notification_type', OrderNotification::NOTIFICATION_REMINDER)
            ->when($orderNotificationId, function($query) use ($orderNotificationId) {
                $query->where('id', $orderNotificationId);
            }, function($query) use ($daysFromBillingDate, $type) {
                $query
                ->when($this->isSingle(), function($query) {
                    $query->where('purchase_type', OrderNotification::PURCHASE_SINGLE);
                }, function($query) use ($type){
                    $query
                        ->where('purchase_type', OrderNotification::PURCHASE_SUBSCRIPTION)
                        ->where('applicable_orders', $this->isInitial()
                            ? OrderNotification::ORDER_FIRST
                            : OrderNotification::ORDER_SUCCEEDING
                        )
                        ->where('subscription_type', $this->subscription->is_auto_charge
                            && $type != 'today'
                                ? OrderNotification::SUBSCRIPTION_AUTO_CHARGE
                                : OrderNotification::SUBSCRIPTION_AUTO_REMIND
                        );
                })
                ->where('days_from_billing_date', $daysFromBillingDate)
            ;
            })
            ->first();

        if (!$notification) {
            throw new BadRequestException('There is no default notification for the selected reminder.');
        }

        return  [
            'title' => replace_placeholders($notification->headline, $this),
            'subtitle' => replace_placeholders($notification->subheader, $this),
            'subject' => replace_placeholders($notification->subject, $this),
            'payment_headline' => replace_placeholders($notification->payment_headline, $this),
            'payment_instructions' => replace_placeholders($notification->payment_instructions, $this),
            'payment_button_label' => $notification->payment_button_label,
            'total_amount_label' => $notification->total_amount_label,
            'payment_instructions_headline' => replace_placeholders($notification->payment_instructions_headline, $this),
            'payment_instructions_subheader' => replace_placeholders($notification->payment_instructions_subheader, $this),
            'type' => $type,
            'has_pay_button' => (bool) $notification->payment_button_label,
            'has_edit_button' => true,
            'has_order_summary' => $hasOrderSummary,
        ];
    }


    /**
     * Send payment reminder to customer through email and mobile number.
     *
     * @param  string  $time.
     * @return self
     */
    public function sendPaymentReminder($time)
    {
        $customer = $this->subscription->customer;

        if (!$customer->email && !$customer->mobile_number) {
            throw new BadRequestException("Selected customer neither have an email and mobile number.");
        }

        $subscription = $this->subscription;
        $merchant = $subscription->merchant;
        $orderProduct  = $this->products->first();

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products)
            && $this->payment_status_id != PaymentStatus::PAID;

        switch ($time) {
            case 'before':
                $options = $this->setReminderOptions(
                    'before',
                    $hasOrderSummary,
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $this,
                            $options
                        )
                    );
                }

                $subscription->messageCustomer($customer, 'payment', $this, 'before',$options);

                break;

            case 'today':
                $options = $this->setReminderOptions(
                    'today',
                    $hasOrderSummary,
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $this,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $this, 'today', $options);

                break;

            case 'after':
                $options = $this->setReminderOptions(
                    'after',
                    $hasOrderSummary,
                );

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $this,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $this, 'after', $options);

                break;

            case 'edit':
                $title = "Edit your {$merchant->subscription_term_singular}";
                $subtitle = "Your changes will be automatically confirmed once update is clicked";

                $instructionHeadline = replace_placeholders(
                        $this->isSingle()
                            ? 'Payment is due on {billingDate}'
                            : 'Next Payment is due on {nextBillingDate}',
                        $this
                    );

                $instructionSubheader = replace_placeholders(
                        $this->isSingle()
                            ? 'Please pay on or before {billingDate}'
                            : 'You will be reminded for your next billing',
                        $this
                    );

                $options = [
                        'title' => $title,
                        'subtitle' => $subtitle,
                        'subject' => "Edit Your {$merchant->subscription_term_singular} #{$subscription->id}",
                        'payment_headline' =>'',
                        'payment_instructions' => '',
                        'payment_button_label' => '',
                        'total_amount_label' => 'Total Amount',
                        'payment_instructions_headline' => $instructionHeadline,
                        'payment_instructions_subheader' => $instructionSubheader,
                        'type' => 'edit',
                        'has_pay_button' => false,
                        'has_edit_button' => true,
                        'has_order_summary' => $this->isInitial(),

                    ];

                if ($customer->email) {
                    $customer->notify(
                        new PaymentReminder(
                            $subscription,
                            $merchant,
                            $orderProduct,
                            $this,
                            $options
                        ));
                }

                $subscription->messageCustomer($customer, 'payment', $this, 'edit', $options);

                break;

            default:
                //
                break;
        }
        return $this;
    }

    /**
     * Generate invoice.
     *
     * @return self
     */
    public function generatePdfOrderInvoice($action = null)
    {
        $merchant = $this->subscription->merchant;
        $customer = $this->subscription->customer;
        $subscription = $this->subscription;
        $order = $this;

        $invoiceNo = $subscription->id .'-'. formatId($order->created_at, $order->id);

        $merchantName = Str::replace(' ', '-', strtoupper($merchant->name));
        $billingDate = str_replace('-', '',$order->billing_date->toDateString());
        $tag = $order->paid_at ? 'P' : 'U';

        $directory = "files/subscriptions/{$subscription->id}/attachments";
        $fileName = "{$billingDate}-{$merchantName}-INVOICE-No-{$invoiceNo}-{$tag}.pdf";
        $filePath = "{$directory}/{$fileName}";

        if (Attachment::where('subscription_id', $subscription->id)->where('name',$fileName)->exists() && !$action) return;

        $pdf = Pdf::loadView('pdf.invoice', compact('merchant','customer','order','subscription'));

        Storage::put($filePath, $pdf->output());

        /*
        * Allow to save the updated invoice in the storage
        */
        if ($action == 'update') return;

        if ($order->paid_at) {
            $unpaidInvoice = $subscription->attachments()
                ->where('name', "{$billingDate}-{$merchantName}-INVOICE-No-{$invoiceNo}-U.pdf")
                ->first();
            if ($unpaidInvoice) {
                $unpaidInvoice->delete();
                $order->attachments()->detach($unpaidInvoice->id);
            }
        }

        $attachment = $order->attachments()->where('name', $fileName)->first();

        if (!$attachment) {
            $attachment = $subscription->attachments()->make([
                'file_path' => $filePath,
                'name' => $fileName,
                'size' => Storage::size($filePath),
                'is_invoice' => true,
            ]);

            $attachment->save();
        }

        $order->attachments()->attach($attachment->fresh()->id);
    }


}
