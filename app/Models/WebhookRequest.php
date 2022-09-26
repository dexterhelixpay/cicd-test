<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookRequest extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'request_method',
        'request_url',
        'request_headers',
        'request_body',
        'response_status',
        'response_headers',
        'response_body',
        'error_info',
        'is_successful',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'request_headers' => 'collection',
        'request_body' => 'collection',
        'response_headers'=> 'collection',
        'response_body' => 'collection',
        'error_info' => 'collection',
        'is_successful' => 'boolean',
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
     * Get the webhook.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
