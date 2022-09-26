<?php

namespace App\Libraries\Viber;

use GuzzleHttp\Client;

class Viber
{
    /**
     * The PayMongo API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The Viber Bot Auth Token.
     *
     * @var string
     */
    protected $authToken;

    /**
     * The sender name
     *
     * @var string
     */
    protected $senderName;

    /**
     * The sender avatar
     *
     * @var string
     */
    protected $senderAvatar;

    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @param  string  $authToken
     * @return void
     */
    public function __construct($apiUrl, $authToken, $senderName, $senderAvatar)
    {
        $this->apiUrl = $apiUrl;
        $this->authToken = $authToken;
        $this->senderName = $senderName;
        $this->senderAvatar = $senderAvatar;
    }

     /**
     * Set the viber credentials
     *
     * @param  string  $authToken
     * @param  string  $secretKey
     * @param  string  $senderAvatar
     *
     * @return self
     */
    public function setViberCredentials($authToken, $senderName, $senderAvatar)
    {
        $this->authToken = $authToken;
        $this->senderName = $senderName;
        $this->senderAvatar = $senderAvatar;

        return $this;
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
            'headers' => $this->getHeaders(),
        ]);
    }

     /**
     * Send a new Messsage "message" HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function message()
    {
        $baseUri = $this->apiUrl . '/send_message';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders(),
        ]);
    }

    /**
     * Setup webhook "message" HTTP client.
     *
     * @return \GuzzleHttp\Client
     */
    public function webhook()
    {
        $baseUri = $this->apiUrl . '/set_webhook';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders(),
        ]);
    }

    /**
     * Get sender invo
     *
     * @return  array
     */
    public function getSender()
    {
        return [
            'name' => $this->senderName,
            'avatar' => $this->senderAvatar
        ];
    }

    /**
     * Run a transaction using temporary keys.
     *
     * @param  string  $token
     * @param  Closure  $callback
     * @return mixed
     */
    public function withToken($token, $callback)
    {
        $originalAuthToken = $this->authToken;

        $this->setToken($token);

        $result = $callback();

        $this->setToken($originalAuthToken);

        return $result;
    }


    /**
     * Set the token.
     *
     * @param  string  $token
     * @return self
     */
    public function setToken($token)
    {
        $this->authToken = $token;

        return $this;
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
            'X-Viber-Auth-Token' => $this->authToken,
        ];
    }
}
