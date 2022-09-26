<?php

namespace App\Events;

use App\Models\ImportBatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopifyProductsImported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The import batch.
     *
     * @var \App\Models\ImportBatch
     */
    public $importBatch;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\ImportBatch  $importBatch
     *
     * @return void
     */
    public function __construct(ImportBatch $importBatch)
    {
        $this->importBatch = $importBatch;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('shopify-import.'.$this->importBatch->id);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'shopify-products-imported';
    }
}
