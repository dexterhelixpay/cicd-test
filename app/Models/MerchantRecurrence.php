<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantRecurrence extends RecordableModel
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',

        'name',
        'code',
        'description',
        'recurrence_day',
        'buffer_days',
        'recurrence_month',
        'sort_number',

        'is_enabled',
        'days_before_overdue',

        'is_discount_label_enabled',
        'subheader',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'is_discount_label_enabled' => 'boolean'
    ];

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
