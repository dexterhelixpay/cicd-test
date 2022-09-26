<?php

namespace App\Jobs\WebhookEvents;

use App\Models\Order;

class OrderShipped extends PostEvent
{
    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    protected $order;

    /**
     * Create a new webhook event job instance.
     *
     * @param  \App\Models\Order  $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getEvent(): string
    {
        return 'order.skipped';
    }

    /**
     * Get the event data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->order->fresh('products', 'subscription')->toArray();
    }
}
