<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymayaMid extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',
        'mid',
        'business_segment',
        'mdr',
        'mcc',
        'is_vault',
        'is_pwp',
        'is_console_created'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_vault' => 'boolean',
        'is_pwp' => 'boolean',
        'is_console_created' => 'boolean'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'masked_mcc',
    ];


    /**
     * Get the public key.
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function getPublicKeyAttribute($value)
    {
        if (!$value) {
            return $value;
        }

        return Str::mask($value, '•', 11);
    }


    /**
     * Get the masked mcc
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function getMaskedMccAttribute()
    {
        if (!$this->mcc) {
            return $this->mcc;
        }

        return Str::mask($this->mcc, '•', 2);
    }

    /**
     * Get the secret key.
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function getSecretKeyAttribute($value)
    {
        if (!$value) {
            return $value;
        }

        return Str::mask($value, '•', 11);
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

}
