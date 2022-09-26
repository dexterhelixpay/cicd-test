<?php

namespace App\Libraries\Brankas;

use GuzzleHttp\Client;

class Brankas
{
    /**
     * The Brankas API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The Brankas Api Key
     *
     * @var string
     */
    protected $apiKey;


    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @param  array  $apiKey
     * @return void
     */
    public function __construct($apiUrl, $apiKey)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * Create a new Brankas "checkout" HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function checkout()
    {
        $baseUri = $this->apiUrl . '/v1/checkout';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders(),
        ]);
    }


    /**
     * Create a new Brankas "transfer" HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function transfer()
    {
        $baseUri = $this->apiUrl . '/v1/transfer';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders(),
        ]);
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
            'x-api-key' => $this->apiKey
        ];
    }
}
