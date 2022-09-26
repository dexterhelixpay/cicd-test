<?php

namespace App\Http\Controllers\Api\v1\Merchant;


use App\Http\Controllers\Controller;
use App\Jobs\SyncShopifyProduct;
use App\Libraries\Shopify\Rest\Metafield;
use App\Libraries\Shopify\Shopify;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;

class ShopifyProductController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store', 'update', 'destroy');
        $this->middleware('auth:user,merchant,null')->only('index', 'show');
        $this->middleware('permission:CP: Merchants - Edit|MC: Products')->only('store', 'update', 'destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $page = collect($request->input('page', []));
        $number = $page->get('number') ?? 1;
        $size = $number ? $page->get('size', 3) : null;

        $shopifyProducts = [];

        if ($merchant->shopify_info) {
            if (
                !Redis::exists("merchants:{$merchant->id}:shopify_products")
                && !Cache::has("merchants:{$merchant->id}:shopify_products:loading")
            ) {
                Cache::put("merchants:{$merchant->id}:shopify_products:loading", 1, 3600);

                $shopify = new Shopify(
                    $merchant->shopify_domain, $merchant->shopify_info['access_token']
                );

                $shopify->createBulkOperationsWebhook();
                $shopify->getAllProducts();
            } else {
                $length = Redis::llen("merchants:{$merchant->id}:shopify_products");

                $shopifyProducts = collect(range(0, $length))
                    ->flatMap(function ($length, $index) use($merchant) {
                        $data = Redis::lrange("merchants:{$merchant->id}:shopify_products", $index, $length);

                        return json_decode(Arr::first($data), true);
                    });
            }
        }

        $importedShopifyProducts = $merchant->products()
            ->whereNotNull('shopify_sku_id')
            ->get()
            ->pluck('shopify_sku_id')
            ->toArray();

        $shopifyProducts = collect($shopifyProducts)
            ->filter(function ($product) use($request, $importedShopifyProducts) {
                return $request->input('filter.is_imported')
                    ? in_array($product['legacyResourceId'], $importedShopifyProducts)
                    : !in_array($product['legacyResourceId'], $importedShopifyProducts);
            });


        if ($request->filled('filter.name.like')) {
            $shopifyProducts = collect($shopifyProducts)
                ->filter(function ($product) use($request) {
                    return mb_stripos($product['title'], $request->input('filter.name.like')) !== false;
                });
        }

        $products = collect($shopifyProducts)
            ->when($number, function (Collection $products, $number) use ($size) {
                return $products->skip(($number - 1) * $size);
            })
            ->when($size, function (Collection $products, $size) {
                return $products->take($size);
            })
            ->values();

        return $this->okResponse(['data' => [
            'hasFilter' => !count($products)
                && intval(Cache::get("merchants:{$merchant->id}:shopify_products:count", 0) > 0),
            'hasCachedProducts' => Cache::has("merchants:{$merchant->id}:shopify_products:count"),
            'products' => $products,
            'meta' => [
                'total' => count($shopifyProducts ?? []),
                'current_page' => $number ?? 1
            ]
        ]])->header('Content-Type', 'application/vnd.api+json');
    }

     /**
     * Store a newly created resource in storage.
     *
     * @param  array $shopifyProducts
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     */
    public function bulkUpdateMetafields($shopifyProducts, Merchant $merchant)
    {
        $data = collect($shopifyProducts)
            ->mapWithKeys(function($shopifyProduct, $index) use($merchant) {
                $shopifyProduct = data_get($shopifyProduct, 'data.attributes');

                $bukopayMetafield = collect($shopifyProduct['metafields'])->where('key', 'recurrence')->first();

                Cache::put(
                    "shopify-products:{$shopifyProduct['legacyResourceId']}:metafields-changed",
                    true,
                    1200
                );

                return [$index => [
                    'input' => [
                        'id' => $shopifyProduct['id'],
                        'metafields' => [collect($bukopayMetafield)->only([
                            'id',
                            'namespace',
                            'key',
                            'value'
                        ])->toArray()]
                    ]
                ]];
            })->toArray();

        $shopify = new Shopify($merchant->shopify_domain, $merchant->shopify_info['access_token']);

        $response = $shopify->uploadFile(Shopify::toJsonl($data));

        $target = Arr::first($response->json('data.stagedUploadsCreate.stagedTargets'));
        ['value' => $key] = collect(data_get($target, 'parameters'))->firstWhere('name', 'key');

        $response = $shopify->bulkMutate('mutation call($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    metafields {
                        edges {
                            node {
                                id
                                key
                                namespace
                                ownerType
                                type
                                value
                            }
                        }
                    }
                }
            }
        }', $key);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function store(Request $request, Merchant $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.products' => 'required|array',
            'data.attributes.products' => 'required|array',
            'data.attributes.products.data.*.attributes.id' => 'required',
            'data.attributes.products.data.*.attributes.metafields' => 'required',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $this->bulkUpdateMetafields(
                $request->input('data.attributes.products'),
                $merchant
            );

            $batch = $merchant->importBatches()->make([
                'total' => 1,
                'pending' => 1,
                'failed' => 0
            ]);
            $batch->save();


            $key = "merchants:{$merchant->id}:for_import_shopify_products";

            Cache::put(
                $key,
                $request->input('data.attributes.products'),
                3600
            );

            dispatch(new SyncShopifyProduct($key, $merchant->id, $batch->id));

            return $this->okResponse(['batch_id' => $batch->id])
                ->header('Content-Type', 'application/vnd.api+json');
        });
    }

     /**
     * Reload Shopify Products
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function reload(Request $request, Merchant $merchant)
    {
        if (!$merchant) {
            throw (new ModelNotFoundException)->setModel(Merchant::class);
        }

        Redis::del("merchants:{$merchant->id}:shopify_products");
        Cache::delete("merchants:{$merchant->id}:shopify_products:count");

        return $this->okResponse();
    }
}
