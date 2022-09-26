<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionImport extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscription_count',
        'purchase_count',
        'links_opened_count',
        'total_amount',
        'purchased_amount',
        'ltv_amount',
        'purchase_percentage',
        'open_percentage'
    ];

    /**
     * Get all the imported subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get all the schedule email.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function scheduleEmail(): BelongsTo
    {
        return $this->belongsTo(ScheduleEmail::class);
    }

    /**
     * Get the subscription import.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

}
