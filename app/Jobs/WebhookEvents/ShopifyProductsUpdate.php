<?php

namespace App\Jobs\WebhookEvents;

use App\Events\ShopifyProductsImported;
use App\Models\ImportBatch;
use App\Models\OrderedProduct;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\SubscribedProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ShopifyProductsUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The shopify products
     *
     * @var string
     */
    protected $cacheKey;

    /**
     * The merchant id
     *
     * @var int
     */
    protected $merchantId;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($cacheKey, $merchantId)
    {
        $this->cacheKey = $cacheKey;
        $this->merchantId = $merchantId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shopifyProduct = Cache::get($this->cacheKey, []);

        Cache::forget($this->cacheKey);

        Product::disableRecording();
        ProductVariant::disableRecording();
        ProductImage::disableRecording();
        ProductOption::disableRecording();
        ProductOptionValue::disableRecording();
        SubscribedProduct::disableRecording();
        OrderedProduct::disableRecording();

        if (!Arr::has($shopifyProduct, 'variants')) {
            return Log::channel('stack')
                ->error('No variants found', [
                    'merchant_id' => $this->merchantId,
                    'shopifyProductId' => data_get($shopifyProduct, 'id'),
                    'file' => __FILE__,
                    'line' => __LINE__,
                ]);
        }

        try {
            DB::beginTransaction();

            $defaultVariant = collect($shopifyProduct['variants'])
                ->where('title', 'Default Title')
                ->first();

            if ($defaultVariant) {
                $defaultVariant['compareAtPrice'] = $defaultVariant['compare_at_price'];
            }

            $shopifyProductId = $shopifyProduct['id'];

            $dataQuery = [
                'shopify_sku_id' => $shopifyProductId,
                'merchant_id' => $this->merchantId
            ];

            $products = Product::query()
                ->withTrashed()
                ->where('shopify_sku_id', $shopifyProductId)
                ->where('merchant_id', $this->merchantId);

            if ($products->count() >= 1) {
                $dataQuery = array_merge($dataQuery, [
                    'id' => $products->latest()->first()->id
                ]);
            }

            $product = Product::withTrashed()->updateOrCreate(
                $dataQuery,
                [
                    'title' => $shopifyProduct['title'],
                    'description' => $shopifyProduct['body_html'],
                    'is_visible' => true,
                    'shopify_info' => Arr::except($shopifyProduct, 'variants'),
                    'is_shopify_product' => true,
                    'price' => $defaultVariant ? $defaultVariant['price'] : null,
                    'original_price' => $defaultVariant ? $defaultVariant['compareAtPrice'] : null,
                    'deleted_at' => null
                ]
            );

            DB::commit();

            $product->load('merchant');

            if ($product->wasRecentlyCreated) {
                $product->are_multiple_orders_allowed = true;
                $product->sort_number = $product->merchant->products()->max('sort_number') + 1;
                $product->save();
            }

            $metafield = collect($shopifyProduct['metafields'])->where('key', 'recurrence')->first();

            if (!$metafield) return;

            $metafield['value'] = is_array($metafield['value'])
                ? $metafield['value']
                : json_decode($metafield['value'], true);

            $shopifyProduct['options'] = array_merge($shopifyProduct['options'], [[
                'name' => 'Frequency',
                'code' => 'recurrence',
                'values' => collect($metafield['value'])->map(function ($value) {
                    return [
                        'name' => $value['label'],
                        'value' => $value['code']
                    ];
                })->toArray()
            ]]);

            dispatch(function () use($product, $shopifyProduct) {
                $product->syncShopifyGroups(data_get($shopifyProduct, 'collections', []));
            })->afterResponse();

            dispatch(function () use($product, $shopifyProduct) {
                $product->syncShopifyImages(data_get($shopifyProduct, 'images', []), true);
            })->afterResponse();

            dispatch(function () use($product, $metafield) {
                $product->syncShopifyProductRecurrences(data_get($metafield, 'value', []));
            })->afterResponse();

            dispatch(function () use($product, $shopifyProduct) {
                $product->syncOptions(data_get($shopifyProduct, 'options', []));
            })->afterResponse();

            dispatch(function () use($product, $shopifyProduct, $shopifyProductId) {
                $product->syncShopifyVariants(
                    data_get($shopifyProduct, 'variants', []),
                    true,
                    $shopifyProductId
                );
            })->afterResponse();
        } catch (Throwable $e) {
            \Log::channel('stack')
                ->error($e->getMessage(), [
                    'merchant_id' => $this->merchantId,
                    'shopifyProductId' => data_get($shopifyProduct, 'id'),
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'trace' => $e->getTraceAsString(),
                ]);
            DB::rollBack();
        }

        Product::enableRecording();
        ProductVariant::enableRecording();
        ProductImage::enableRecording();
        ProductOption::enableRecording();
        ProductOptionValue::enableRecording();
        SubscribedProduct::enableRecording();
        OrderedProduct::enableRecording();
    }

}
