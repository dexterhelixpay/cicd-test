<?php

namespace App\Libraries;

use App\Libraries\Shopify\Customer;
use App\Libraries\Shopify\Order;
use App\Libraries\Shopify\Product;
use App\Libraries\Shopify\ProductVariant;
use Illuminate\Support\Facades\Http;

class Shopify
{
    /**
     * The Shopify HTTP client
     *
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $client;

    /**
     * Create a new Shopify instance.
     *
     * @param  string  $shopUrl
     * @param  string  $authToken
     * @return void
     */
    public function __construct($shopUrl, $authToken)
    {
        $this->client = Http::asJson()
            ->baseUrl($this->prependScheme($shopUrl))
            ->withHeaders([
                'Accepts' => 'application/json',
                'X-Shopify-Access-Token' => $authToken,
            ]);
    }

    /**
     * Create a new customers API instance.
     *
     * @param  string  $shopUrl
     * @param  string  $accessToken
     * @return \App\Libraries\Shopify\Customer
     */
    public function customers($shopUrl, $accessToken)
    {
        return new Customer($shopUrl, $accessToken);
    }

    /**
     * Create a new orders API instance.
     *
     * @param  string  $shopUrl
     * @param  string  $accessToken
     * @return \App\Libraries\Shopify\Order
     */
    public function orders($shopUrl, $accessToken)
    {
        return new Order($shopUrl, $accessToken);
    }

    /**
     * Create a new products API instance.
     *
     * @param  string  $shopUrl
     * @param  string  $accessToken
     * @return \App\Libraries\Shopify\Product
     */
    public function products($shopUrl, $accessToken)
    {
        return new Product($shopUrl, $accessToken);
    }

    /**
     * Create a new product variants API instance.
     *
     * @param  string  $shopUrl
     * @param  string  $accessToken
     * @return \App\Libraries\Shopify\ProductVariant
     */
    public function productVariants($shopUrl, $accessToken)
    {
        return new ProductVariant($shopUrl, $accessToken);
    }

    /**
     * Get the products with the given IDs.
     *
     * @param  array  $ids
     * @return \Illuminate\Http\Client\Response
     */
    public function getProductsById($ids)
    {
        $queries = collect($ids)->map(function ($id, $index) {
            $id = "\"gid://shopify/Product/{$id}\"";

            return "product{$index}: product(id: {$id}) {
                id
                descriptionHtml
                hasOnlyDefaultVariant
                images(first: 2) {
                    edges {
                        node {
                            id
                            url
                        }
                    }
                }
                legacyResourceId
                title
                variants(first: 5) {
                    edges {
                        node {
                            id
                            compareAtPrice
                            displayName
                            inventoryItem {
                                requiresShipping
                            }
                            legacyResourceId
                            price
                            sku
                            title
                        }
                    }
                }
            }";
        })->join(' ');

        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => "query { {$queries} }",
        ]);
    }

    /**
     * Get the product variants with the given IDs.
     *
     * @param  array  $ids
     * @return \Illuminate\Http\Client\Response
     */
    public function getVariantsById($ids)
    {
        $queries = collect($ids)->map(function ($id, $index) {
            $id = "\"gid://shopify/ProductVariant/{$id}\"";

            return "productVariant{$index}: productVariant(id: {$id}) {
                id
                compareAtPrice
                displayName
                inventoryItem {
                    requiresShipping
                }
                legacyResourceId
                price
                product {
                    id
                    descriptionHtml
                    images(first: 2) {
                        edges {
                            node {
                                id
                                url
                            }
                        }
                    }
                    legacyResourceId
                    title
                }
                selectedOptions {
                    name
                    value
                }
                sku
                title
            }";
        })->join(' ');

        return $this->client->post('admin/api/2022-01/graphql.json', [
            'query' => "query { {$queries} }",
        ]);
    }

    /**
     * Prepend scheme to the given URL.
     *
     * @param  string  $url
     * @return string
     */
    private function prependScheme($url)
    {
        return parse_url($url, PHP_URL_SCHEME) === null
            ? 'https://' . $url
            : $url;
    }
}
