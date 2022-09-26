<?php

namespace App\Libraries\PayMaya\v2;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class Api
{
    /**
     * The PayMaya API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @return void
     */
    public function __construct($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * Get the API client.
     *
     * @param  string|null  $key
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function client(?string $key = null)
    {
        return Http::asJson()
            ->baseUrl($this->apiUrl)
            ->when($key, function (PendingRequest $request, $key) {
                $request->withToken(base64_encode("{$key}:") , 'Basic');
            });
    }
}
