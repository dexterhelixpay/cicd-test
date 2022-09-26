<?php

namespace App\Http\Controllers\Api\v1;

use App\Libraries\Shopify\Rest\Script;
use App\Models\Merchant;
use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Jobs\SyncShopifyProduct;
use App\Jobs\InstallShopifyPrerequisite;
use App\Jobs\SyncShopifyGroups;
use App\Jobs\WebhookEvents\ShopifyProductsUpdate;
use App\Libraries\Shopify\Shopify;
use App\Models\MerchantProductGroup;
use App\Models\OrderedProduct;
use App\Models\Product;
use App\Models\SubscribedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use GuzzleHttp\Client;
use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ShopifyController extends Controller
{
    /**
     * Install shopify to merchant
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function installShopify(Request $request)
    {
        $merchant = $this->validateShopifyMerchant($request);

        if ($merchant->shopify_info) {
            return view('shopify.index', [
                'buttonCode' => '<button
    type="button"
    class="bukopay-create-subs-button"
    data-shop-domain="{{ shop.domain }}"
>
    Create Subscription
</button>'
            ]);
        }

        $shop = $request->input('shop');
        $apiKey = $merchant->shopify_api_key;
        $scopes = join(',', [
            'read_customers',
            'write_customers',
            'read_inventory',
            'write_inventory',
            'read_orders',
            'write_orders',
            'read_products',
            'write_products',
            'write_script_tags',
        ]);
        $redirectUri = env('APP_URL') . '/v1/shopify/generate_token';

        $installUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

        return Redirect::away($installUrl);
    }

    /**
     * Install shopify to merchant
     *
     * @param  \Illuminate\Http\Request  $request
     * @param string $shop
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateShopifyMerchant(Request $request, $shop = null)
    {
        $shopDomain = $shop ?: $request->input('shop') ?: data_get(json_decode($request->input('data')), 'shop');

        $merchant = Merchant::where('shopify_domain', $shopDomain)->orWhere('shopify_store_link', $shopDomain)->first();

        if (!$merchant) abort(404);

        return $merchant;
    }

    /**
     * Update shopify token
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateToken(Request $request)
    {
        $merchant = $this->validateShopifyMerchant($request);

        return DB::transaction(function () use ($request, $merchant) {
            $merchant->forceFill(['shopify_info' => $request->all()])->update();

            return new Resource($merchant->fresh());
        });
    }

    /**
     * Redirect to storefront
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToStorefront(Request $request)
    {
        $merchant = $this->validateShopifyMerchant($request);

        $scheme = app()->isLocal() ? 'http' : 'https';
        $summaryUrl = config('bukopay.url.shopify_summary');

        $storeFrontUrl = $merchant->custom_domain
            && $merchant->custom_domain_verified_at
            && $merchant->is_custom_domain_used
            ? "{$scheme}://{$merchant->custom_domain}/shopify/summary"
            : "{$scheme}://{$merchant->subdomain}.{$summaryUrl}";

        return view('shopify.form', [
            'url' => $storeFrontUrl,
            'cartData' => json_decode($request->input('data'), true)
        ]);
    }

    /**
     * Clean up shopify store
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cleanUp(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.merchant_id' => [
                'required',
                Rule::exists('merchants', 'id')->whereNull('deleted_at')
            ]
        ]);

        $merchant = Merchant::find($request->input('data.attributes.merchant_id'));

        $shopify = new Shopify(
            $merchant->shopify_domain, $merchant->shopify_info['access_token']
        );

        $response = json_decode(
            $shopify->deleteFrequencyMetafieldDefinition($merchant->shopify_info['metafield_definition_id']),
            true
        );

        return Arr::has($response, 'errors')
            ? $this->errorResponse()
            : $this->okResponse();
    }


    /**
     * Capture Shopfiy Webhookss
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function captureWebhooks(Request $request)
    {
        if (
            $request->server('HTTP_X_SHOPIFY_TOPIC') == 'products/update'
            && Cache::get(
                "shopify-products:{$request->input('id')}:metafields-changed",
                false
            )
        ) {
            Cache::forget("shopify-products:{$request->input('id')}:metafields-changed");
            return $this->okResponse();
        }

        $merchant = Merchant::where('shopify_domain', $request->server('HTTP_X_SHOPIFY_SHOP_DOMAIN'))->first();

        if ($merchant) {
            switch ($request->server('HTTP_X_SHOPIFY_TOPIC')) {
                case 'app/uninstalled':
                    $merchant->forceFill([
                        'shopify_info' => null
                    ])->update();
                    break;

                case 'collections/update':
                    dispatch(new SyncShopifyGroups($request->all(), $merchant->id))->afterResponse();
                    break;

                case 'collections/delete':
                    if (!$request->has('id')) return;

                    $merchant->productGroups()->where('shopify_collection_id', $request->input('id'))->delete();
                    break;

                case 'products/update':
                    if (!$merchant->products()->where('shopify_sku_id', $request->input('id'))->first()) {
                        return;
                    }

                    $time = str_replace(':', '_', now()->toTimeString());

                    $key = "merchants:{$merchant->id}:{$time}:webhook_shopify_products";
                    Cache::put(
                        $key,
                        $request->all(),
                        3600
                    );

                    dispatch(new ShopifyProductsUpdate($key, $merchant->id))->afterResponse();
                    break;

                case 'products/delete':
                    Cache::forget("merchants:{$merchant->id}:shopify_products");
                    break;

                case 'products/create':
                    break;
            }
        }

        return $this->okResponse();
    }

    /**
     * Install shopify to merchant
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function generateToken(Request $request)
    {
        $merchant = $this->validateShopifyMerchant($request);

        $apiKey = $merchant->shopify_api_key;
        $secretKey = $merchant->shopify_secret_key;
        $params = $request->all();
        $hmac = $request->input('hmac');

        $params = array_diff_key($params, array('hmac' => ''));
        ksort($params);

        $computed_hmac = hash_hmac('sha256', http_build_query($params), $secretKey);

        return DB::transaction(function () use ($merchant, $hmac, $computed_hmac, $params, $apiKey, $secretKey) {
            if (hash_equals($hmac, $computed_hmac)) {
                try {
                    $response = (new Client)->post("https://{$params['shop']}/admin/oauth/access_token", [
                        'form_params' => [
                            "client_id" => $apiKey,
                            "client_secret" => $secretKey,
                            "code" => $params['code']
                        ],
                    ]);

                    $body = json_decode($response->getBody(), true);

                    $merchant->forceFill(['shopify_info' => $body])->update();

                    $scriptTagUrl = config('bukopay.url.script_tag');
                    $randomString = Str::random(5);

                    (new Script($merchant->shopify_domain, $body['access_token']))
                        ->create([
                            'event' => 'onload',
                            'src' => "{$scriptTagUrl}?buko={$randomString}",
                            'display_scope' => 'all',
                        ])->then(function () {
                        })
                        ->wait(false);

                    dispatch(new InstallShopifyPrerequisite($merchant))->afterResponse();

                    return Redirect::away("https://{$params['shop']}/admin/apps");
                } catch (Throwable $e) {
                    abort(404);
                }
            }
        });
    }
}
