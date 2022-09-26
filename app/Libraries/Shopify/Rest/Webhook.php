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

class Webhook
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
     * Create Shopify Order
     *
     * @param  array  $parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function create($paramaters)
    {
        try {
            $response = $this->client()->post('/admin/api/2021-10/webhooks.json', [
                'json' => [
                    'webhook' => $paramaters
                ]
            ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Delete Shopify Webhook
     *
     * @param  id  $parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function delete($id)
    {
        try {
            $response = $this->client()->delete("/admin/api/2021-10/webhooks/{$id}.json");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
