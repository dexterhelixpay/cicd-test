<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkout extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'max_payment_count',
        'success_redirect_url',
        'failure_redirect_url',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'merchant_id',
        'customer',
        'products',
        'subscription',
        'max_payment_count',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'customer' => 'array',
        'expires_at' => 'datetime',
        'products' => 'array',
        'subscription' => 'array',
    ];

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
