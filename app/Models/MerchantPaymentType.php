<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class MerchantPaymentType extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'merchant_payment_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'is_globally_enabled',
        'is_enabled',
        'payment_methods',

        'convenience_label',
        'convenience_fee',
        'convenience_type_id'
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'is_globally_enabled' => 'boolean',
        'payment_methods' => 'array',
    ];

    /**
     * Get the customer who used the voucher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the customer who used the voucher.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }
}
