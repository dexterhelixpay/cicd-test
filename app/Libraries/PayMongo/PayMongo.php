<?php

namespace App\Libraries\PayMongo;

use GuzzleHttp\Client;

class PayMongo
{
    /**
     * The PayMongo API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The PayMongo secret key.
     *
     * @var string
     */
    protected $secretKey;

    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @param  string  $secretKey
     * @return void
     */
    public function __construct($apiUrl, $secretKey)
    {
        $this->apiUrl = $apiUrl;
        $this->secretKey = $secretKey;
    }

    /**
     * Create a new PayMongo HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function client()
    {
        return new Client([
            'base_uri' => $this->apiUrl,
            'auth' => [$this->secretKey, null],
        ]);
    }
}
