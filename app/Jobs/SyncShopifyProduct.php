<?php

namespace App\Jobs;

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
use Illuminate\Support\Facades\Redis;
use Throwable;

class SyncShopifyProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    public $timeout = 7200;

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
     * is from webhook
     *
     * @var bool
     */
    protected $isWebhookUpdate;

    /**
     * The batch id
     *
     * @var int
     */
    protected $batchId;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($cacheKey, $merchantId, $batchId = null, $isWebhookUpdate = false)
    {
        $this->cacheKey = $cacheKey;
        $this->merchantId = $merchantId;
        $this->isWebhookUpdate = $isWebhookUpdate;
        $this->batchId = $batchId;

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shopifyProducts = Cache::get($this->cacheKey, []);

        Cache::forget($this->cacheKey);

        Product::disableRecording();
        ProductVariant::disableRecording();
        ProductImage::disableRecording();
        ProductOption::disableRecording();
        ProductOptionValue::disableRecording();
        SubscribedProduct::disableRecording();
        OrderedProduct::disableRecording();

        collect($shopifyProducts)
            ->each(function ($shopifyProduct) {
                try {
                    DB::beginTransaction();

                    $shopifyProduct = $this->isWebhookUpdate
                        ? $shopifyProduct
                        : data_get($shopifyProduct, 'data.attributes');

                    $defaultVariant = collect($shopifyProduct['variants'])
                        ->where('title', 'Default Title')
                        ->first();

                    if ($defaultVariant && $this->isWebhookUpdate) {
                        $defaultVariant['compareAtPrice'] = $defaultVariant['compare_at_price'];
                    }

                    $shopifyProductId = $this->isWebhookUpdate
                        ? $shopifyProduct['id']
                        : $shopifyProduct['legacyResourceId'];

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
                            'description' => $this->isWebhookUpdate
                                ? $shopifyProduct['body_html']
                                : $shopifyProduct['descriptionHtml'],
                            'is_visible' => true,
                            'shopify_info' => Arr::except($shopifyProduct, 'variants'),
                            'is_shopify_product' => true,
                            'price' => $defaultVariant ? $defaultVariant['price'] : null,
                            'original_price' => $defaultVariant ? $defaultVariant['compareAtPrice'] : null,
                            'deleted_at' => null
                        ]
                    );

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

                    $product->syncShopifyGroups(data_get($shopifyProduct, 'collections', []));
                    $product->syncShopifyImages(data_get($shopifyProduct, 'images', []), $this->isWebhookUpdate);
                    $product->syncShopifyProductRecurrences(data_get($metafield, 'value', []));
                    $product->syncOptions(data_get($shopifyProduct, 'options', []));
                    $product->syncShopifyVariants(
                        data_get($shopifyProduct, 'variants', []),
                        $this->isWebhookUpdate,
                        $shopifyProductId
                    );

                    DB::commit();
                } catch (Throwable $e) {
                    \Log::channel('stack')
                        ->error($e->getMessage(), [
                            'merchantId' => $this->merchantId,
                            'shopifyProductId' => data_get($shopifyProduct, 'id'),
                            'file' => __FILE__,
                            'line' => __LINE__,
                            'trace' => $e->getTraceAsString(),
                        ]);

                    DB::rollBack();
                }
            });

        Product::enableRecording();
        ProductVariant::enableRecording();
        ProductImage::enableRecording();
        ProductOption::enableRecording();
        ProductOptionValue::enableRecording();
        SubscribedProduct::enableRecording();
        OrderedProduct::enableRecording();

        $this->decrementPending();
    }

    /**
     *  Update batch
     *
     * @return void
     */
    protected function decrementPending()
    {
        if (!$this->batchId) return;

        $importBatch = ImportBatch::whereKey($this->batchId)->first();

        if (!$importBatch) return;

        $pending = $importBatch->pending - 1;

        $importBatch->forceFill([
            'finished_at' => $pending == 0 ? now()->toDateTimeString() : null,
            'pending' => $pending
        ])->update();

        if ($pending == 0) {
            Redis::del("merchants:{$this->merchantId}:shopify_products");
            Cache::delete("merchants:{$this->merchantId}:shopify_products:count");
            ShopifyProductsImported::dispatch($importBatch);
        }
    }

    /**
     *  Update batch
     *
     * @return void
     */
    protected function incrementFailed()
    {
        if (!$this->batchId) return;

        $importBatch = ImportBatch::whereKey($this->batchId)->first();

        if (!$importBatch) return;

        $failed = $importBatch->failed + 1;
        $pending = $importBatch->pending - 1;

        $importBatch->forceFill([
            'finished_at' => $pending == 0 ? now()->toDateTimeString() : null,
            'failed' => $failed,
            'pending' => $pending
        ])->update();

        if ($pending == 0) {
            Redis::del("merchants:{$this->merchantId}:shopify_products");
            Cache::delete("merchants:{$this->merchantId}:shopify_products:count");
            ShopifyProductsImported::dispatch($importBatch);
        }
    }

}
