<?php

namespace App\Jobs;

use App\Models\Merchant;
use App\Models\User;
use App\Notifications\ShopifyProductsIdentificationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class IdentifyShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

      /**
     * The merchant's ID.
     *
     * @var int
     */
    public $merchantId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($merchantId)
    {
        $this->merchantId = $merchantId;
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

        $email = Cache::get("merchants:{$this->merchantId}:shopify_products:identifying:to", '');

        if (!$user = User::where('email', $email)->first()) {
            return;
        }

        if (!Cache::has("merchants:{$merchant->id}:shopify_products")) {
            return;
        }

        $type = Cache::get("merchants:{$merchant->id}:shopify_products:identifying:type");
        $shopifyProducts = Cache::get("merchants:{$merchant->id}:shopify_products", []);

        $importedShopifyProducts = $merchant->products()
            ->whereNotNull('shopify_sku_id')
            ->get()
            ->pluck('shopify_sku_id')
            ->toArray();

        $products = collect($shopifyProducts)
            ->filter(function ($product) use($type, $importedShopifyProducts) {
                return $type
                    ? in_array($product['legacyResourceId'], $importedShopifyProducts)
                    : !in_array($product['legacyResourceId'], $importedShopifyProducts);
            });

        Cache::put("merchants:{$this->merchantId}:shopify_products:identifying:formatted_products", $products, 3600);

        $user->notify(new ShopifyProductsIdentificationNotification(
            $this->merchantId,
            $type ? 'Imported' : 'Unimported',
            collect($products)->count()
        ));
    }
}
