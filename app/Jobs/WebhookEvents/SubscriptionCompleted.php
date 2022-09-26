<?php

namespace App\Jobs\WebhookEvents;

use App\Models\Subscription;

class SubscriptionCompleted extends PostEvent
{
    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
     */
    protected $subscription;

    /**
     * Create a new webhook event job instance.
     *
     * @param  \App\Models\Subscription  $subscription
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getEvent(): string
    {
        return 'subscription.completed';
    }

    /**
     * Get the event data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->subscription->fresh('customer', 'products')->toArray();
    }
}
