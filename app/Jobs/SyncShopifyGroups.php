<?php

namespace App\Jobs;

use App\Models\MerchantProductGroup;
use Illuminate\Bus\Queueable;
use App\Libraries\Shopify\Shopify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncShopifyGroups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The merchant id
     *
     * @var int
     */
    protected $merchantId;

    /**
     * The shopify collection
     *
     * @var array
     */
    protected $collection;

    /**
     * Create a new job instance.
     *topic
     * @return void
     */
    public function __construct($collection, $merchantId)
    {
        $this->merchantId = $merchantId;
        $this->collection = $collection;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $productGroup = MerchantProductGroup::updateOrCreate(
            [
                'shopify_collection_id' => $this->collection['id'],
                'merchant_id' => $this->merchantId
            ],
            [
                'name' => $this->collection['title'],
                'shopify_info' => array_merge($this->collection, [
                    'legacyResourceId' => $this->collection['id'],
                    'id' => $this->collection['admin_graphql_api_id']
                ]),
                'is_shopify_group' => true,
                'is_visible' => true,
            ]
        );

        if ($productGroup->wasRecentlyCreated) {
            $productGroup->sort_number = $productGroup
                ->merchant
                ->productGroups()
                ->count();

            $productGroup->save();
        }

        $shopify = new Shopify(
            $productGroup->merchant->shopify_domain, $productGroup->merchant->shopify_info['access_token']
        );

        $shopify->createBulkOperationsWebhook();
        $shopify->getAllProductsViaCollection();


        $collectionIds = Cache::get("merchants:{$this->merchantId}:shopify_collections:collection_ids", []);

        Cache::put(
            "merchants:{$this->merchantId}:shopify_collections:collection_ids",
            array_merge($collectionIds, [$this->collection['id']]),
            3600
        );
    }
}
