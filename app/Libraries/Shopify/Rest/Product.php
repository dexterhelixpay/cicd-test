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

class Product
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
     * Get Shopify Products that are active
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function get($parameters = [])
    {
        try {
            $response = $this->client()->get('/admin/api/2021-10/products.json');

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }


    /**
     * Get Shopify Products based
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function getById($id)
    {
        try {
            $response = $this->client()->get("/admin/api/2021-10/products.json?since_id={$id}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
