<?php

namespace App\Libraries\Shopify\Rest;

use App\Libraries\Shopify\Rest\Shopify;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use Illuminate\Support\Facades\Cache;

class Metafield
{
    /**
     * The Shop URL.
     *
     * @var string
     */
    protected $shopUrl;

    /**
     * The Auth Token.
     *
     * @var string
     */
    protected $authToken;


    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $shopUrl
     * @param  string  $authToken
     * @return void
     */
    public function __construct($shopUrl, $authToken)
    {
        $this->shopUrl = "https://{$shopUrl}";
        $this->authToken = $authToken;
    }

    /**
     * Get the request headers.
     *
     * @param  string  $type
     * @param  bool  $secret
     * @return array
     */
    protected function getHeaders()
    {
        return [
            'Content-Type' => 'application/json',
            'X-Shopify-Access-Token' => $this->authToken,
        ];
    }

    /**
     * Create a new PayMongo HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    protected function client()
    {
        return new Client([
            'base_uri' => "{$this->shopUrl}",
            'headers' => $this->getHeaders(),
        ]);
    }

    /**
     * Create Shopify Metafield Definition
     *
     * @param array $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function createDefinition()
    {
        try {
            $response = $this->client()->post("/admin/api/unstable/graphql.json", [
                'json' => [
                    'query' => '
                        mutation {
                            metafieldDefinitionCreate (
                                definition: {
                                    name: "Frequency",
                                    namespace: "bukopay",
                                    key: "recurrence",
                                    type: "json",
                                    description: "Available Frequency",
                                    ownerType: PRODUCT,
                                }
                            ) {
                                createdDefinition {
                                    id,
                                    ownerType
                                }
                            }
                        }
                    '
                    ]
            ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Create Shopify Metafields
     *
     * @param  string  $id
     * @param  string  $type
     * @param array $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function create($id, $type, $parameters)
    {
        try {
            $response = $this->client()->post("/admin/api/2021-10/{$type}/{$id}/metafields.json", [
                'json' => [
                    'metafield' => $parameters
                ]
            ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }


    /**
     * Get  Shopify Metafield based on type and id
     *
     * @param  string  $id
     * @param  string  $type
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function get($id, $type)
    {
        try {
            $response = $this->client()->get("/admin/{$type}/{$id}/metafields.json");

            Cache::tags('shopify')->put('limit_count',
                $response->getHeader('X-Shopify-Shop-Api-Call-Limit'),
                now()->addMinute()
            );

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Update Shopify Metafield based on type and id
     *
     * @param  string  $typeId
     * @param  string  $type
     * @param  string  $metafieldId
     * @param  string  $parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function update($typeId, $type, $metafieldId, $parameters)
    {
        try {
            $response = $this->client()->put("/admin/api/2021-10/{$type}/{$typeId}}/metafields/{$metafieldId}.json", [
                'json' => [
                    'metafield' => $parameters
                ]
            ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

     /**
     * Update Shopify Metafield based on type and id
     *
     * @param  string  $typeId
     * @param  string  $type
     * @param  string  $metafieldId
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function delete($typeId, $type, $metafieldId)
    {
        try {
            $response = $this->client()->delete("/admin/api/2021-10/{$type}/{$typeId}}/metafields/{$metafieldId}.json");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
