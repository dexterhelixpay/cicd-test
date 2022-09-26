<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XenditAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fee_unit',
        'fee_amount',
        'overall_paid_transactions_threshold',
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

    /**
     * Check if the merchant has met the overall paid transactions threshold.
     *
     * @return bool
     */
    public function hasMetThreshold()
    {
        return $this->merchant
            && $this->merchant->total_paid_transactions >= $this->overall_paid_transactions_threshold;
    }
}
