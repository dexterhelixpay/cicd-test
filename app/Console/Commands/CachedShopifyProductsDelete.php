<?php

namespace App\Console\Commands;

use App\Models\Merchant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CachedShopifyProductsDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cached-shopify-products:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete cached shopify products';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Merchant::query()
            ->whereNotNull('shopify_info')
            ->cursor()
            ->tapEach(function (Merchant $merchant) {
                if (
                    !Cache::has("merchants:{$merchant->id}:shopify_products:expiration")
                    && Redis::exists("merchants:{$merchant->id}:shopify_products")
                ) {
                    Redis::del("merchants:{$merchant->id}:shopify_products");
                    Cache::delete("merchants:{$merchant->id}:shopify_products:count");
                }
            })->all();
    }
}
