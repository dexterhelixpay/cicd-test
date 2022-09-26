<?php

namespace App\Jobs;

use App\Facades\Shopify;
use App\Libraries\Shopify\Requests\Order as OrderRequest;
use App\Models\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateShopifyOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    protected $order;

    /**
     * Flag if order will be created
     *
     * @var bool
     */
    public $force;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Order  $order
     * @param  bool  $force
     * @return void
     */
    public function __construct($order, $force = false)
    {
        $this->order = $order;
        $this->force = $force;
    }

    /**
     * Execute the job.
     *
     * @return \App\Models\Order
     */
    public function handle()
    {
        $this->order->load('subscription.customer.country', 'subscription.merchant');

        $merchant = $this->order->subscription->merchant;

        if (
            !$merchant->shopify_info
            || ($this->order->order_status_id != OrderStatus::PAID
             && $this->order->order_status_id != OrderStatus::OVERDUE)
            || ($this->order->shopify_order_id && !$this->force)
        ) {
            return;
        }

        $this->order->subscription->customer->createShopifyRecord();

        $response = Shopify::orders(
            $merchant->shopify_domain,
            data_get($merchant, 'shopify_info.access_token')
        )->create((new OrderRequest)->from($this->order));

        if ($response->status() === 429) {
            Log::error('Shopify Order Creation Rate Limited', [
                'order' => $this->order->getKey(),
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return dispatch(new self($this->order))->delay(now()->addMinute());
        }

        if ($response->failed()) {
            return Log::error('Shopify Order Creation Failed', [
                'order' => $this->order->getKey(),
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        $this->order
            ->forceFill(['shopify_order_id' => $response->json('order.id')])
            ->save();

        return $this->order->fresh();
    }
}
