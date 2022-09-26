<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends RecordableModel
{
    /**
     * Constant representing cthe valid events for webhooks.
     *
     * @var array
     */
    const EVENTS = [
        'subscription.created',
        'subscription.updated',
        'subscription.cancelled',
        'payment.success',
        'payment.failed',
        'order.shipped',
        'order.skipped',
        'order.cancelled',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'events',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'events' => 'array',
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
     * Get the requests to this webhook.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function requests(): HasMany
    {
        return $this->hasMany(WebhookRequest::class);
    }
}
