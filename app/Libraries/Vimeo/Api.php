<?php

namespace App\Libraries\Vimeo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class Api
{
    /**
     * Create a new Vimeo API instance.
     *
     * @param  string  $apiUrl
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $accessToken
     * @return void
     */
    public function __construct(
        protected string $apiUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected string $accessToken
    ) {
        //
    }

    /**
     * Get the API client.
     *
     * @param  bool  $withToken
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function client(bool $withToken = false)
    {
        return Http::asJson()
            ->baseUrl($this->apiUrl)
            ->when($withToken, function (PendingRequest $request) {
                $request->withToken($this->accessToken);
            });
    }
}
