<?php

namespace App\Jobs\WebhookEvents;

use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SubscriptionUpdated extends PostEvent implements ShouldBeUnique
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
        return 'subscription.updated';
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

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId()
    {
        return $this->subscription->getKey();
    }
}
