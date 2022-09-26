<?php

namespace App\Models;

use App\Facades\Shopify;
use App\Libraries\PayMaya\Customer as PayMayaCustomer;
use App\Notifications\VerificationCode;
use App\Notifications\VerificationCodeMail;
use App\Notifications\VoucherVerificationMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;

class Customer extends RecordableUser
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'country_id',
        'mobile_number',
        'formatted_mobile_number',

        'address',
        'province',
        'country_name',
        'city',
        'barangay',
        'zip_code',

        'is_unsubscribed',
        'viber_info',
        'other_info',

        'shopify_id',

        'discord_user_id',
        'discord_user_username'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'verification_code',
        'paymaya_uuid',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_unsubscribed' => 'boolean',
        'viber_info' => 'array',
        'other_info' => 'array',
    ];

    /**

    /**
     * Get the customer's cards.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cards(): HasMany
    {
        return $this->hasMany(CustomerCard::class);
    }

    /**
     * Get the customer's paymaya wallets.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(CustomerWallet::class);
    }

    /**
     * Get customer's last request
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function lastRequest(): MorphOne
    {
        return $this->morphOne(LastHttpRequest::class, 'user')->where('is_revoke', false);
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
     * Get the country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the new country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function newCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'new_country_id');
    }


    /**
     * Get the customer's orders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Subscription::class);
    }

    /**
     * Get the membership.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('is_membership', true);
    }

    /**
     * Get all subscriptions having membership products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Subscription::class)
            ->whereRelation('products', 'is_membership', true);
    }

    /**
     * Get the subscribed products with membership.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function membershipProducts(): HasManyThrough
    {
        return $this->hasManyThrough(
                SubscribedProduct::class,
                Subscription::class,
                'customer_id',
                'subscription_id',
                'id',
                'id',
            )
            ->with('product.groups')
            ->where('subscribed_products.is_membership', true);
    }

    /**
     * Get the subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all the vouchers used by the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function usedVouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, UsedVoucher::class)->withPivot('product_limit_info');
    }

    /**
     * Get all the vouchers used by the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function blastVouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'customer_id')
            ->whereNotNull('merchant_email_blast_id');
    }

    /**
     * Get the last created subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * Route notifications for the Viber channel.
     *
     * @param  \Illuminate\Notifications\Notification|null
     * @return string
     */
    public function routeNotificationForViber($notification = null)
    {
        if (!$this->viber_info) return null;

        return $this->viber_info['id'];
    }

    /**
     * Route notifications for the SMS channel.
     *
     * @param  \Illuminate\Notifications\Notification|null
     * @return string
     */
    public function routeNotificationForSms($notification = null)
    {
        if ($newCountry = $this->newCountry()->first()) {
            if ($newCountry->name == 'Philippines') {
                return "{$this->newCountry->dial_code}{$this->mobile_number}";
            }
        }

        return "{$this->country?->dial_code}{$this->mobile_number}";
    }

     /**
     * Get Used Product Limit of voucher
     *
     * @param  int  $voucherId
     * @param  int  $productId
     * @param  int  $orderId
     *
     * @return mixed
     */
    public function getUsedVoucherProductLimit($voucherId = null, $productId = null, $orderId = null)
    {
        return $this
            ->usedVouchers()
            ->where('id', $voucherId)
            ->when($orderId, function ($query) use($orderId) {
                $query->where('order_id', '!=', $orderId);
            })
            ->get()
            ->reduce(function($carry, $usedVoucher) use ($productId) {
                return $carry + data_get($usedVoucher->pivot->product_limit_info, "_{$productId}", 0);
            }, 0);

    }

    /**
     * Send the verification code.
     * @param bool $forUpdate
     * @param string $type
     * @return void
     */
    public function sendVerificationCode($forUpdate = false, $type = 'update', $isMobileNumber = true)
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::withoutEvents(function () use ($code) {
            $this->forceFill(['verification_code' => bcrypt($code)])->save();
        });

        $this->load('country', 'newCountry');

        $hasOtherCountry = $this->country?->code !== 'PH';
        $hasNewOtherCountry = $forUpdate && $this->newCountry?->code !== 'PH';

        if ($hasOtherCountry || $hasNewOtherCountry || !$isMobileNumber) {
            return $this->notify(new VerificationCodeMail($code, $type));
        }

        $this->notify(new VerificationCode($code, $forUpdate));
    }

    /**
     * Send the voucher verification code.
     *
     * @param bool $isMobileNumber
     * @return void
     */
    public function sendVoucherVerificationCode($isMobileNumber = true)
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        static::withoutEvents(function () use ($code) {
            $this->forceFill(['verification_code' => bcrypt($code)])->save();
        });

        if (!$isMobileNumber) {
            return $this->notify(new VoucherVerificationMail($code, $this->merchant));
        }

        $this->notify(new VerificationCode($code));
    }
    /**
     * Create a Shopify record for the customer.
     *
     * @return $this
     */
    public function createShopifyRecord()
    {
        $this->load('country', 'merchant');

        if (!$this->merchant->shopify_domain || !$this->merchant->shopify_info) {
            return $this;
        }

        if ($shopifyId = $this->shopify_id) {
            $response = Shopify::customers(
                $this->merchant->shopify_domain,
                data_get($this->merchant, 'shopify_info.access_token')
            )->find($shopifyId);

            if ($response->successful()) {
                return $this;
            }
        }

        if (Str::validEmail($email = $this->email)) {
            $response = Shopify::customers(
                $this->merchant->shopify_domain,
                data_get($this->merchant, 'shopify_info.access_token')
            )->search(['query' => 'email:' . $email]);

            $customer = collect($response->json('customers', []))->first();

            if ($response->successful() && $customer) {
                $this->setAttribute('shopify_id', data_get($customer, 'id'))->save();

                return $this->refresh();
            }
        }

        $nameParts = Str::splitName($this->name, false);
        $email = Str::validEmail($email) ?: null;
        $phone = $this->country ? $this->formatted_mobile_number : $this->mobile_number;

        $response = Shopify::customers(
            $this->merchant->shopify_domain,
            data_get($this->merchant, 'shopify_info.access_token'),
        )->create(
            collect([
                'first_name' => $nameParts['firstName'],
                'last_name' => $nameParts['lastName'],
                'email' => $email,
                'phone' => $phone,
                'tags' => 'HelixPay',
                'addresses' => [
                    collect([
                        'first_name' => $nameParts['firstName'],
                        'last_name' => $nameParts['lastName'],
                        'email' => $email,
                        'phone' => $phone,
                        'address1' => $this->address,
                        'city' => $this->city,
                        'province' => $this->province,
                        'country' => optional($this->country)->name,
                        'zip' => $this->zip_code,
                    ])->filter()->toArray()
                ],
            ])->filter()->toArray()
        );

        $this->shopify_id = $response->successful()
            ? $response->json('customer.id')
            : null;

        return tap($this)->save();
    }

    /**
     * Create a PayMaya record for the customer.
     *
     * @param  \App\Models\Order|null  $order
     * @return $this
     */
    public function createPayMayaRecord($order = null)
    {
        $nameParts = Str::splitName($this->name);
        $recipientParts = optional($order)->recipient
            ? Str::splitName($order->recipient)
            : $nameParts;

        $contact = collect([
            'phone' => $this->mobile_number,
            'email' => $this->email,
        ])->filter()->toArray();

        $billingAddress = collect([
            'line1' => optional($order)->billing_address ?: $this->address,
            'line2' => optional($order)->billing_barangay ?: $this->barangay,
            'city' => optional($order)->billing_city ?: $this->city,
            'state' => optional($order)->billing_province ?: $this->province,
            'zipCode' => optional($order)->billing_zip_code ?: $this->zip_code,
            'countryCode' => 'PH',
        ])->filter()->toArray();

        $shippingAddress = collect([
            'firstName' => $recipientParts['firstName'],
            'lastName' => $recipientParts['lastName'],
            'phone' => $this->mobile_number,
            'email' => $this->email,
            'line1' => optional($order)->shipping_address ?: $this->address,
            'line2' => optional($order)->shipping_barangay ?: $this->barangay,
            'city' => optional($order)->shipping_city ?: $this->city,
            'state' => optional($order)->shipping_province ?: $this->province,
            'zipCode' => optional($order)->shipping_zip_code ?: $this->zip_code,
            'countryCode' => 'PH',
            'shippingType' => 'ST',
        ])->filter()->toArray();

        $data = [
            'firstName' => $nameParts['firstName'],
            'lastName' => $nameParts['lastName'],
            'customerSince' => $this->created_at->toDateString(),
            'contact' => $contact,
            'billingAddress' => $billingAddress,
            'shippingAddress' => $shippingAddress,
        ];

        PayMayaCustomer::updateOrCreate($this->paymaya_uuid, $data)
            ->then(function ($customer) {
                $this->setAttribute('paymaya_uuid', $customer['id']);
            }, function ($e) {
                throw $e;
            })->wait();

        $this->update();

        return $this;
    }
}
