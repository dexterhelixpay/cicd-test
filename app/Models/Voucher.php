<?php

namespace App\Models;

use App\Exceptions\BadRequestException;
use App\Exceptions\VoucherApplicationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Support\Arr;

class Voucher extends Model
{
    use SoftDeletes;


    /**
     * Constant representing a fixed discount voucher.
     *
     * @var integer
     */
    const FIXED_TYPE = 1;

    /**
     * Constant representing a percentage discount voucher.
     *
     * @var integer
     */
    const PERCENTAGE_TYPE = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',
        'merchant_email_blast_id',
        'customer_id',

        'code',
        'type',

        'amount',
        'total_count',
        'remaining_count',
        'applicable_order_count',

        'minimum_purchase_amount',

        'is_enabled',
        'is_secure_voucher',

        'expires_at',

        'product_limits'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'is_secure_voucher' => 'boolean',
        'product_limits' => 'array',
    ];

    /**
     * Check if the voucher is expired.
     *
     * @return bool
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at
            ? now()->greaterThanOrEqualTo($this->expires_at)
            : false;
    }

    /**
     * A voucher belongs to an merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }


    /**
     * A voucher belongs to an customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }



    /**
     * A voucher belongs to an blast.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function blast(): BelongsTo
    {
        return $this->belongsTo(MerchantEmailBlast::class);
    }


      /**
     * Get all the used vouchers in the specific voucher
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function usedVouchers(): HasMany
    {
        return $this->hasMany(UsedVoucher::class);
    }


      /**
     * Get all the qualified customers in the specific voucher
     *
     * @return \Illuminate\Database\Eloquent\Relations\hasMany
     */
    public function qualifiedCustomers(): HasMany
    {
        return $this->hasMany(VoucherQualifiedCustomer::class);
    }


    /**
     * Get the orders that used this voucher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, UsedVoucher::class);
    }

     /**
     * Generate an unused voucher code.
     *
     * @param  string  $prefix
     * @return string
     */
    public static function generateCode($prefix = 'BP')
    {
        $code = mb_strtoupper($prefix . Str::random(6));

        if (self::where('code', $code)->exists()) {
            return self::generateCode();
        }

        return $code;
    }

    /**
     * Check if the voucher has product limits
     *
     * @return bool
     */
    public function hasProuductLimits(): bool
    {
        return count($this->product_limits ?? []) > 0;
    }

    /**
     * Check if the voucher is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at
            ? now()->greaterThanOrEqualTo($this->expires_at)
            : false;
    }

    /**
     * Use the voucher on the specified order.
     *
     * @param  \App\Models\Order  $order
     * @param bool $throwError
     *
     * @return void
     */
    public function use(Order $order, $throwError = true)
    {
        $subscription = $order->subscription;

        $totalPrice = $order->original_price ?: $order->total_price;

        if (
            !static::validate(
                code: $this->code,
                totalPrice: $totalPrice ?? 0,
                merchantId: $subscription->merchant->id,
                customerId: $subscription->customer_id,
                order: $order,
                throwError: $throwError,
                products: $order->products()->get())
        ) {
            return self::getErrorResponse(code: 2, throwError: $throwError);
        }

        $order->forceFill(['voucher_code' => $this->code])->update();

        $this->attachVoucherToCustomer($order, $subscription);
        $this->takeCustomerSlot($subscription);
        $this->decrement('remaining_count');
    }

    /**
     * Attach voucher to customer
     *
     * @return void
     */
    public function attachVoucherToCustomer($order, $subscription)
    {
        $voucherInfo = ['customer_id' => $subscription->customer->id];

        if ($this->hasProuductLimits()) {
            $voucherInfo['product_limit_info'] = $order->products()
                ->get()
                ->mapWithKeys(function (OrderedProduct $product) use($subscription, $order) {
                    $usedProductCount = $subscription
                        ->customer
                        ->getUsedVoucherProductLimit($this->id, $product->product_id, $order->id);

                    $productLimitCount = data_get($this->product_limits, "_{$product->product_id}", 0);
                    $remainingLimit = $productLimitCount - $usedProductCount;

                    $applicableProductCount = $product->quantity <= $remainingLimit
                        ? $product->quantity
                        : ($remainingLimit % $product->quantity);

                    return ["_{$product->product_id}" => $applicableProductCount];

                });
        }

        $order->voucher()->attach($this->id, $voucherInfo);
    }

    /**
     * Take the slot for the given customer.
     *
     * @return void
     */
    public function takeCustomerSlot($subscription)
    {
        if (!$this->is_secure_voucher || !$subscription->customer) return;

        $customer = $subscription->customer;

        $qualifiedCustomer = $this->qualifiedCustomers()
            ->where(function ($query) use ($customer) {
                $query->where(function ($query) use($customer) {
                        $query->whereJsonContains('mobile_numbers', "0{$customer->mobile_number}")
                            ->orWhereJsonContains('mobile_numbers', $customer->mobile_number);
                    })
                    ->orWhereJsonContains('emails', $customer->email);
            })
            ->first();

        if ($qualifiedCustomer && !$qualifiedCustomer->customer_id) {
            $qualifiedCustomer->forceFill(['customer_id' => $customer->id])->update();
        }
    }

    /**
     * Restore the slot for the given customer.
     *
     * @return void
     */
    public function restoreCustomerSlot($subscription)
    {
        if (!$this->is_secure_voucher || !$subscription->customer) return;

        $customer = $subscription->customer;

        $qualifiedCustomer = $this->qualifiedCustomers()
            ->where(function ($query) use ($customer) {
                $query->where(function ($query) use($customer) {
                        $query->whereJsonContains('mobile_numbers', "0{$customer->mobile_number}")
                            ->orWhereJsonContains('mobile_numbers', $customer->mobile_number);
                     })
                    ->orWhereJsonContains('emails', $customer->email);
            })
            ->first();

        if ($qualifiedCustomer) {
            $usedCount = $customer
                ->usedVouchers()
                ->where('id', $this->id)
                ->count();

            if ($usedCount == 0) {
                $qualifiedCustomer->forceFill(['customer_id' => null])->update();
            }
        }
    }
    /**
     * Apply the given voucher.
     *
     * @param  float  $cost
     * @param  string  $code
     * @return array
     */
    public static function apply(float $cost, string $code): array
    {
        $originalCost = $cost;
        $voucher = static::where('code', $code)->first();

        if ($voucher->type === static::FIXED_TYPE) {
            $cost -= $voucher->amount;
        } elseif ($voucher->type === static::PERCENTAGE_TYPE) {
            $cost -= $cost * ($voucher->amount / 100);
        }

        return [
            'original_cost' => $originalCost,
            'cost' => round($cost < 0 ? 0 : $cost, 2),
            'voucher_code' => $voucher->only('id', 'code'),
        ];
    }

    /**
     * Remove the given voucher.
     *
     * @param  float  $cost
     * @param  string  $code
     * @return int
     */
    public static function remove(float $cost, string $code)
    {
        $voucher = static::where('code', $code)->first();

        if ($voucher->type === static::FIXED_TYPE) {
            $cost += $voucher->amount;
        } elseif ($voucher->type === static::PERCENTAGE_TYPE) {
            $cost += $cost * ($voucher->amount / 100);
        }

        return $cost;
    }



    /**
     * Return validation response
     *
     *
     * @return mixed
     */
    public static function getErrorResponse(
        $code,
        $voucher = null,
        $throwError = true,
        $usageCount = null,
        $merchantName = null
    ) {
        if (!$throwError) return false;

        switch ($code) {
            case 2:
                throw new VoucherApplicationException(
                    'This is not an active voucher code.',
                    $code
                );
                break;

            case 3:
                throw new VoucherApplicationException(
                    'This voucher is no longer active.',
                    $code
                );
                break;

            case 4:
                throw new VoucherApplicationException(
                    "Minimum order value to use this voucher code is {$voucher->minimum_purchase_amount}",
                    $code
                );
                break;

            case 9:
                throw new VoucherApplicationException(
                    "You can only use this voucher {$usageCount} time(s)",
                    $code
                );
                break;

            case 10:
                throw new VoucherApplicationException(
                    'This voucher is for specific customer only',
                    $code
                );
                break;

            case 13:
                throw new VoucherApplicationException(
                    'You used all of your remaining discounts for this voucher',
                    $code
                );
                break;

            case 14:
                throw new VoucherApplicationException(
                    'Sorry, this voucher cannot be used with this product',
                    $code
                );
                break;

            case 15:
                throw new VoucherApplicationException(
                   "This voucher code is not linked to your account. Please change your email or mobile number or contact {$merchantName}",
                    $code
                );
                break;

            case 16:
                throw new VoucherApplicationException(
                    'You have already used this voucher in a different account',
                    $code
                );
                break;

            case 17:
                throw new VoucherApplicationException(
                    'Your order is already free!',
                    $code
                );
                break;

            default:
                return false;
                break;
        }
    }

      /**
     * Validate if the specified voucher is usable.
     *
     * @param  string  $code
     * @param  int  $totalPrice
     * @return mixed
     */
    public static function validate(
        string $code,
        int $totalPrice = 0,
        int $merchantId = null,
        int $customerId = null,
        $order = null,
        bool $throwError = true,
        $products = []
    ) {
        $voucher = static::where('merchant_id', $merchantId)->where('code', $code)->first();
        $isPaidOrder = $order && $order->order_status_id == OrderStatus::PAID;

        if (
            (!$voucher
            || !$voucher->is_enabled) && !$isPaidOrder
        ) {
            return self::getErrorResponse(code: 2, throwError: $throwError);
        }

        if (
            ($voucher->total_count <= 0
            || $voucher->remaining_count <= 0
            || $voucher->isExpired()) && !$isPaidOrder
        ) {
            return self::getErrorResponse(code: 3, throwError: $throwError);
        }

        if (
            $voucher->minimum_purchase_amount
            && ($voucher->minimum_purchase_amount > $totalPrice)
        ) {
            return self::getErrorResponse(code: 4, throwError: $throwError, voucher: $voucher);
        }

        if ($totalPrice == 0) {
            return self::getErrorResponse(code: 17, throwError: $throwError);
        }

        $customer = Customer::find($customerId);

        if (($usageCount = $voucher->applicable_order_count) && $customer) {
            $usedCount = $customer
                ->usedVouchers()
                ->where('id', $voucher->id)
                ->count();

            if ($usedCount >= $usageCount) {
                return self::getErrorResponse(code: 9, throwError: $throwError, usageCount: $usageCount);
            }
        }

        if ($voucher->is_secure_voucher && $customer) {
            $qualifiedCustomer = $voucher->qualifiedCustomers()
                ->where(function ($query) use ($customer) {
                    $query->where(function ($query) use($customer) {
                            $query->whereJsonContains('mobile_numbers', "0{$customer->mobile_number}")
                                ->orWhereJsonContains('mobile_numbers', $customer->mobile_number);
                        })
                        ->orWhereJsonContains('emails', $customer->email);
                })
                ->first();

            if (!$qualifiedCustomer) {
                return self::getErrorResponse(code: 15, throwError: $throwError, merchantName: $voucher->merchant->name);
            }

            $isVoucherTaken = $qualifiedCustomer
                && $qualifiedCustomer->customer_id
                && $qualifiedCustomer->customer_id != $customer->id;

            if ($isVoucherTaken) {
                return self::getErrorResponse(code: 16, throwError: $throwError);
            }
        }

        if ($voucher->hasProuductLimits()) {
            $hasApplicableProductDiscount = collect($products)
                ->contains(function ($product) use ($voucher) {
                    $productId = self::getProductInfo($product, 'id');

                    return Arr::has(
                        $voucher->product_limits,
                        "_{$productId}"
                    );
                });

            if (!$hasApplicableProductDiscount) {
                return self::getErrorResponse(code: 14, throwError: $throwError);
            }

            if ($customer) {
                $productRemaining = collect($voucher->product_limits)
                    ->reduce(function($carry, $limit, $key)  use ($customer, $voucher, $order) {
                        $productId = str_replace("_", "", $key);

                        $usedProductCount = $customer->getUsedVoucherProductLimit($voucher->id, $productId, $order?->id);
                        $remainingLimit = $limit - $usedProductCount;

                        if ($remainingLimit <= 0) {
                            return $carry;
                        }

                        return $carry + $remainingLimit;
                    }, 0);

                if ($productRemaining <= 0) {
                    return self::getErrorResponse(code: 13, throwError: $throwError);
                }
            }
        }

        if ($voucher->customer_id && (optional($customer)->id !== $voucher->customer_id)) {
            return self::getErrorResponse(code: 10, throwError: $throwError);
        }

        return $voucher;
    }


    /**
     * Compute total discount
     *
     * @param  mixed  $totalPrice
     *
     * @return mixed
     */
    public function getDiscount($totalPrice = 0)
    {
        if ($this->type === Voucher::FIXED_TYPE) {
            return $this->amount;
        } elseif ($this->type === Voucher::PERCENTAGE_TYPE) {
            return $totalPrice * ($this->amount / 100);
        }
    }

    /**
     * Get Product Info
     *
     * @param  array  $product
     *
     * @return mixed
     */
    public static function getProductInfo($product, $type = 'price')
    {
        if ($type == 'price') {
            if (data_has($product, 'variantPrice')) {
               return data_get($product, 'variantPrice');
            } elseif (data_has($product, 'recurrencePrice')) {
               return data_get($product, 'recurrencePrice');
            } elseif (data_has($product, 'price')) {
               return data_get($product, 'price');
            }
        }

        $productId = data_get($product, 'product_id')
            ?? data_get($product, 'id');

        if ($variantId = data_get($product, 'variantId')) {
            $variant = ProductVariant::find($variantId);

            if ($variant) {
                $productId = $variant->product_id;
            }
        }

        return $productId;
    }


    /**
     * Compute total discount
     *
     * @param  array  $options
     *
     * @return array
     */
    public function computeTotalDiscount($options = [])
    {
        extract($options);

        if (!$this->hasProuductLimits()) {
            return ['discount_amount' => $this->getDiscount($totalPrice) ?? 0];
        }

        $productLimitDiscounts = [];

        $discountAmount = $products
            ->reduce(function($carry, $product) use(&$productLimitDiscounts, $customer, $order) {
                if (!$quantity = data_get($product, 'quantity')) {
                    return $carry;
                }

                $productId = self::getProductInfo($product, 'product_id');

                $productLimitCount = data_get($this->product_limits, "_{$productId}", 0);
                $remainingLimit = $productLimitCount;

                if ($customer) {
                    $usedProductCount = $customer->getUsedVoucherProductLimit($this->id, $productId, $order?->id);
                    $remainingLimit = $productLimitCount - $usedProductCount;

                    if ($remainingLimit <= 0) {
                        return $carry;
                    }
                }

                $applicableProductCount = $quantity <= $remainingLimit
                    ? $quantity
                    : ($remainingLimit % $quantity);

                $price = self::getProductInfo($product) ?? 0;
                $discountAmount = $applicableProductCount * $this->getDiscount($price);

                $productLimitDiscounts["_{$productId}"] = $discountAmount;

                return $carry + $discountAmount;
            }, 0);

        return [
            'discount_amount' => $discountAmount,
            'product_voucher_discounts' => $productLimitDiscounts
        ];
    }

    /**
     * Set voucher code to uppercase before we save it to the database.
     *
     * @param  string  $value
     * @return void
     */
    public function setCodeAttribute($value)
    {
        $this->attributes['code'] = strtoupper($value);
    }

}
