<?php

namespace App\Http\Controllers\Api\v1\Shopify;

use App\Events\ShopifyProductsLoaded;
use App\Http\Controllers\Controller;
use App\Jobs\CacheShopifyProducts;
use App\Jobs\SyncShopifyGroupProducts;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ProductController extends Controller
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureTopic(Request $request)
    {
        $type = $request->input('type');

        if (!$topic = $request->header('x-shopify-topic')) {
            return response()->json();
        }

        if (!$shopDomain = $request->header('x-shopify-shop-domain')) {
            return response()->json();
        }

        if (!$merchant = Merchant::where('shopify_domain', $shopDomain)->first()) {
            return response()->json();
        }

        switch ($topic) {
            case 'bulk_operations/finish':
                if ($type === 'query') {
                    dispatch(new CacheShopifyProducts(
                        $merchant->getKey(),
                        $request->input('admin_graphql_api_id')
                    ));
                } elseif ($type === 'mutation') {
                    //
                }

                break;
        }

        return response()->json();
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cacheProducts(Request $request)
    {
        $data = $request->input('data');
        $merchant = Merchant::find($request->input('merchantId'));

        if (!$request->filled('data') || !$merchant) return response()->json();

        $collectionOnlyKeys = [
            'id',
            'legacyResourceId',
            'products'
        ];

        $isFetchingProductsViaCollection = Arr::has(Arr::first($data), $collectionOnlyKeys)
            && count(Arr::except(Arr::first($data), $collectionOnlyKeys)) === 0;

        if ($isFetchingProductsViaCollection) {
            $cacheKey = now()->toTimeString();

            Redis::rpush("merchants:{$merchant->id}:shopify_collections:{$cacheKey}", json_encode($data));

            if ($request->input('isLastData')) {
                dispatch(new SyncShopifyGroupProducts($merchant->getKey(), $cacheKey));
            }
        } else {
            Redis::rpush("merchants:{$merchant->id}:shopify_products", json_encode($data));

            if (!Cache::has("merchants:{$merchant->id}:shopify_products:count")) {
                Cache::set("merchants:{$merchant->id}:shopify_products:count", count($data));
            } else {
                Cache::increment("merchants:{$merchant->id}:shopify_products:count", count($data));
            }

            if ($request->input('isLastData')) {
                Cache::delete("merchants:{$merchant->id}:shopify_products:loading");
                Cache::put("merchants:{$merchant->id}:shopify_products:expiration", 1, 2400);
                ShopifyProductsLoaded::dispatch($merchant->id);
            }
        }

        return response()->json();
    }
}
