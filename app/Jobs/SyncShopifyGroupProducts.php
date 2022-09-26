<?php

namespace App\Jobs;

use App\Models\MerchantProductGroup;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class SyncShopifyGroupProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant id
     *
     * @var int
     */
    protected $merchantId;

    /**
     * The cache key
     *
     * @var string
     */
    protected $cacheKey;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($merchantId, $cacheKey)
    {
        $this->merchantId = $merchantId;
        $this->cacheKey = $cacheKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $length = Redis::llen("merchants:{$this->merchantId}:shopify_collections:{$this->cacheKey}");
        $data = Redis::lrange("merchants:{$this->merchantId}:shopify_collections:{$this->cacheKey}", 0, $length);

        $collections = collect($data)
            ->flatMap(function ($data) {
                return json_decode($data, true);
            });

        $collectionIds = Cache::get(
            "merchants:{$this->merchantId}:shopify_collections:collection_ids",
            []
        );

        if (!count($collections) || !count($collectionIds)) return;

        collect($collections)
            ->filter(function ($collection) use($collectionIds) {
                return in_array($collection['legacyResourceId'], $collectionIds);
            })
            ->each(function ($collection) use($collectionIds) {
                $productGroup = MerchantProductGroup::where(
                    'shopify_collection_id',
                    $collection['legacyResourceId']
                )->first();

                if (!$productGroup) return;

                $productGroup->products()->detach();

                if (!Arr::has($collection, 'products')) return;

                collect($collection['products'])
                    ->each(function ($product) use($productGroup) {
                        $product = Product::where(
                            'shopify_sku_id',
                            $product['legacyResourceId']
                        )->first();

                        if (!$product) return;

                        $productExist = $productGroup->products()
                            ->where('product_id', $product->id)
                            ->exists();

                        if (!$productExist) {
                            $productGroup->products()->attach($product->id, [
                                'sort_number' => $productGroup->products()->count() + 1
                            ]);
                        }
                    });


                $filteredCollectionIds = collect($collectionIds)
                    ->filter(function ($id) use ($collection) {
                        return $id != $collection['legacyResourceId'];
                    })->values()->all();

                Cache::put(
                    "merchants:{$this->merchantId}:shopify_collections:collection_ids",
                    $filteredCollectionIds,
                    3600
                );
            });

        Redis::delete("merchants:{$this->merchantId}:shopify_collections:{$this->cacheKey}");
    }
}
