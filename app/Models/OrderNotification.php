<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderNotification extends RecordableModel
{
    /**
     * Constant representing a notification for single orders.
     *
     * @var string
     */
    const PURCHASE_SINGLE = 'SINGLE';

    /**
     * Constant representing a notification for subscriptions.
     *
     * @var string
     */
    const PURCHASE_SUBSCRIPTION = 'SUBSCRIPTION';

    /**
     * Constant representing a payment notification.
     *
     * @var string
     */
    const NOTIFICATION_PAYMENT = 'PAYMENT';

    /**
     * Constant representing a reminder notification.
     *
     * @var string
     */
    const NOTIFICATION_REMINDER = 'REMINDER';

    /**
     * Constant representing an auto charge subscription.
     *
     * @var string
     */
    const SUBSCRIPTION_AUTO_CHARGE = 'AUTO_CHARGE';

    /**
     * Constant representing an auto remind subscription.
     *
     * @var string
     */
    const SUBSCRIPTION_AUTO_REMIND = 'AUTO_REMIND';

    /**
     * Constant representing an auto charge subscription.
     *
     * @var string
     */
    const ORDER_FIRST = 'FIRST';

    /**
     * Constant representing an auto remind subscription.
     *
     * @var string
     */
    const ORDER_SUCCEEDING = 'SUCCEEDING';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'merchant_id',

        'purchase_type',
        'notification_type',
        'subscription_type',
        'applicable_orders',
        'days_from_billing_date',
        'is_payment_successful',
        'has_payment_lapsed',
        'recurrences',

        'subject',
        'headline',
        'subheader',

        'payment_headline',
        'payment_instructions',
        'payment_button_label',

        'total_amount_label',

        'payment_instructions_headline',
        'payment_instructions_subheader',

        'is_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'recurrences' => 'array',
        'is_payment_successful' => 'boolean',
        'has_payment_lapsed' => 'boolean',
        'is_enabled' => 'boolean',
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
