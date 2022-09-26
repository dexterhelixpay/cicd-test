<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantFollowUpEmail extends Model
{
    use HasFactory;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',

        'days',

        'subject',

        'headline',
        'body',

        'is_enabled',
        'recurrences'
    ];

       /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_enabled' => 'boolean',
        'recurrences' => 'array'
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
     * Replace the terms
     *
     * @param  \App\Models\Order $order
     * @param string $text
     *
     * @return string
     */
    public function replaceTerms($order, $text)
    {
        $subscription = $order->subscription;
        $merchant = $subscription->merchant;

        $month = Carbon::parse($order->billing_date)->format('F');
        $day = ordinal_number(Carbon::parse($order->billing_date)->format('j'));
        $billingDate = "{$month} {$day}";

        $text = str_replace(
            '{subscriptions}', $merchant->subscription_term_plural ?? 'subscriptions', $text
        );

        $text = str_replace(
            '{subscription}', $merchant->subscription_term_singular ?? 'subscription', $text
        );

        $text = str_replace(
            '{billingDate}', $billingDate, $text
        );

        $text = str_replace(
            '{merchantName}', $merchant->name, $text
        );

        $text = str_replace(
            '{subscriptionId}', formatId($subscription->created_at, $subscription->id), $text
        );

        $text = str_replace(
            '{orderId}', formatId($order->created_at, $order->id), $text
        );

        return $text;
    }


}
