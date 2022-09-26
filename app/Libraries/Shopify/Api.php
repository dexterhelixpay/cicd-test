<?php

namespace App\Libraries\Shopify;

use Illuminate\Support\Facades\Http;

abstract class Api
{
    /**
     * The partner's Shopify URL.
     *
     * @var string
     */
    protected $shopUrl;

    /**
     * The partner's access token.
     *
     * @var string
     */
    protected $accessToken;

    /**
     * Create a new Shopify instance.
     *
     * @param  string  $shopUrl
     * @param  string  $accessToken
     * @return void
     */
    public function __construct(string $shopUrl, string $accessToken)
    {
        $this->shopUrl = $shopUrl;
        $this->accessToken = $accessToken;
    }

    /**
     * Get the API client.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function client()
    {
        return Http::asJson()
            ->baseUrl($this->prependScheme($this->shopUrl))
            ->withHeaders(['X-Shopify-Access-Token' => $this->accessToken]);
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
