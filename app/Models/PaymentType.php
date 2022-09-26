<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PaymentType extends RecordableModel
{
    /**
     * Constant representing a GCash transaction.
     *
     * @var int
     */
    const GCASH = 1;

    /**
     * Constant representing a GrabPay transaction.
     *
     * @var int
     */
    const GRABPAY = 2;

    /**
     * Constant representing a credit/debit card transaction.
     *
     * @var int
     */
    const CARD = 3;

    /**
     * Constant representing a cash transaction.
     *
     * @var int
     */
    const CASH = 4;


    /**
     * Constant representing a bank transfer transaction
     *
     * @var int
     */
    const BANK_TRANSFER = 5;

    /**
     * Constant representing a paymaya wallet transaction
     *
     * @var int
     */
    const PAYMAYA_WALLET = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'icon_path',

        'is_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    /**
     * Get the icon path.
     *
     * @return string
     */
    public function getIconPathAttribute($value)
    {
        return $value ? Storage::url($value) : null;
    }

    /**
     * Get the error responses.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function errorResponses(): HasMany
    {
        return $this->hasMany(PaymentErrorResponse::class);
    }
}
