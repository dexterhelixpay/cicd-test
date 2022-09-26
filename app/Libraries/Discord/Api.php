<?php

namespace App\Libraries\Discord;

use Illuminate\Support\Facades\Http;

abstract class Api
{
    /**
     * The Discord API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The Discord Redirect URL.
     *
     * @var string
     */
    protected $redirectUrl;

    /**
     * The Discord Client ID.
     *
     * @var string|null
     */
    protected $clientId;

    /**
     * The Discord Client Secret.
     *
     * @var string|null
     */
    protected $clientSecret;

    /**
     * The Discord Discord Bot Token.
     *
     * @var string|null
     */
    protected $botToken;

    /**
     * Create a new Discord instance.
     *
     * @param  string  $apiUrl
     * @return void
     */
    public function __construct(
        $apiUrl,
        $redirectUrl,
        $clientId = null,
        $clientSecret = null,
        $botToken = null
    ) {
        $this->apiUrl = $apiUrl;
        $this->redirectUrl = $redirectUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->botToken = $botToken;
    }

    /**
     * Get the API client.
     *
     */
    public function client()
    {
        return Http::asJson()
            ->baseUrl($this->apiUrl)
            ->withHeaders([
                'Authorization' => "Bot {$this->botToken}",
            ]);
    }

    /**
     * Get the API form client.
     *
     */
    public function formClient()
    {
        return Http::asForm()
            ->baseUrl($this->apiUrl)
            ->withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]);
    }

    /**
     * Get the API form client.
     *
     * @param  string|null  $token
     */
    public function userClient(?string $token)
    {
        return Http::asJson()
            ->baseUrl($this->apiUrl)
            ->withHeaders([
                'Authorization' => "Bearer {$token}"
            ]);
    }

}
