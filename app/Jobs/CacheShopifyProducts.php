<?php

namespace App\Jobs;

use App\Events\ShopifyProductsLoaded;
use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CacheShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant's ID.
     *
     * @var int
     */
    public $merchantId;

    /**
     * The bulk operation's ID.
     *
     * @var string
     */
    public $bulkOperationId;

    /**
     * Create a new job instance.
     *
     * @param  int  $merchantId
     * @param  string  $bulkOperationId
     * @return void
     */
    public function __construct($merchantId, $bulkOperationId)
    {
        $this->merchantId = $merchantId;
        $this->bulkOperationId = $bulkOperationId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!$merchant = Merchant::find($this->merchantId)) {
            return;
        }

        $shopify = new Shopify($merchant->shopify_domain, $merchant->shopify_info['access_token']);

        $response = $shopify->getBulkOperation($this->bulkOperationId);

        if ($response->failed() || !($url = $response->json('data.node.url'))) {
            return;
        }

        Redis::del("merchants:{$merchant->id}:shopify_products");
        Cache::delete("merchants:{$merchant->id}:shopify_products:count");

        try {
            return Http::post(config('bukopay.url.shopify_parse_url'), [
                'url' => $url,
                'merchantId' => $merchant->id,
                'callbackUrl' => in_array(env('APP_ENV'), ['local', 'development', 'staging'])
                    ? env('APP_URL')
                    : null
            ]);
        } catch (Throwable $e) {
            \Log::info($e->getMessage());
            return;
        }
    }
}
