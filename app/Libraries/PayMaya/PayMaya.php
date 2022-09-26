<?php

namespace App\Libraries\PayMaya;

use GuzzleHttp\Client;

class PayMaya
{
    /**
     * The PayMaya API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The PayMaya PwP keys.
     *
     * @var array
     */
    protected $pwpKeys;

    /**
     * The PayMaya Vault keys.
     *
     * @var string
     */
    protected $vaultKeys;

    /**
     * Create a new PayMaya instance.
     *
     * @param  string  $apiUrl
     * @param  array  $pwpKeys
     * @param  array  $vaultKeys
     * @return void
     */
    public function __construct($apiUrl, $pwpKeys, $vaultKeys)
    {
        $this->apiUrl = $apiUrl;
        $this->pwpKeys = $pwpKeys;
        $this->vaultKeys = $vaultKeys;
    }

    /**
     * Create a new PayMaya "checkout" HTTP client.
     *
     * @param  bool  $secret
     * @return \GuzzleHttp\Client
     */
    public function checkout($secret = false)
    {
        $baseUri = $this->apiUrl . "/checkout/v1/";

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders('pwp', $secret),
        ]);
    }

    /**
     * Get the keys of the given type.
     *
     * @param  string  $type
     * @return array
     */
    public function getKeys($type)
    {
        return $this->{"{$type}Keys"};
    }

    /**
     * Create a new PayMaya "payby" HTTP client.
     *
     * @param  bool  $secret
     * @return \GuzzleHttp\Client
     */
    public function payBy($secret = false)
    {
        $baseUri = $this->apiUrl . '/payby/v2/paymaya/';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders('pwp', $secret),
        ]);
    }

    /**
     * Create a new PayMaya "payments" HTTP client.
     *
     * @param  bool  $secret
     * @return \GuzzleHttp\Client
     */
    public function payments($secret = false)
    {
        $baseUri = $this->apiUrl . '/payments/v1/';

        return new Client([
            'base_uri' => $baseUri,
            'headers' => $this->getHeaders('vault', $secret),
        ]);
    }

    /**
     * Set the PwP keys.
     *
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @return self
     */
    public function setPwpKeys($publicKey, $secretKey)
    {
        $this->pwpKeys = [
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
        ];

        return $this;
    }

    /**
     * Set the vault keys.
     *
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @return self
     */
    public function setVaultKeys($publicKey, $secretKey)
    {
        $this->vaultKeys = [
            'public_key' => $publicKey,
            'secret_key' => $secretKey,
        ];

        return $this;
    }

    /**
     * Run a transaction using temporary PwP keys.
     *
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @param  Closure  $callback
     * @return mixed
     */
    public function withPwpKeys($publicKey, $secretKey, $callback)
    {
        [
            'public_key' => $originalPublicKey,
            'secret_key' => $originalSecretKey,
        ] = $this->pwpKeys;

        $this->setPwpKeys($publicKey, $secretKey);

        $result = $callback();

        $this->setPwpKeys($originalPublicKey, $originalSecretKey);

        return $result;
    }

    /**
     * Run a transaction using temporary vault keys.
     *
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @param  Closure  $callback
     * @return mixed
     */
    public function withVaultKeys($publicKey, $secretKey, $callback)
    {
        [
            'public_key' => $originalPublicKey,
            'secret_key' => $originalSecretKey,
        ] = $this->vaultKeys;

        $this->setVaultKeys($publicKey, $secretKey);

        $result = $callback();

        $this->setVaultKeys($originalPublicKey, $originalSecretKey);

        return $result;
    }

    /**
     * Get the request headers.
     *
     * @param  string  $type
     * @param  bool  $secret
     * @return array
     */
    protected function getHeaders($type = 'vault', $secret = false)
    {
        $key = $this->{"{$type}Keys"}[$secret ? 'secret_key' : 'public_key'];

        return [
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '. base64_encode("{$key}:"),
        ];
    }
}
