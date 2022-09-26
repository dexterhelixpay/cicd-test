<?php

namespace App\Libraries;

use App\Libraries\Xendit\Account;
use App\Libraries\Xendit\CallbackUrl;
use App\Libraries\Xendit\EWalletCharge;
use App\Libraries\Xendit\FeeRule;
use Illuminate\Support\Facades\Http;

class Xendit
{
    /**
     * The Xendit API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The Xendit public key.
     *
     * @var string
     */
    protected $publicKey;

    /**
     * The Xendit secret key.
     *
     * @var string
     */
    protected $secretKey;

    /**
     * Create a new Xendit instance.
     *
     * @param  string  $apiUrl
     * @param  string  $publicKey
     * @param  string  $secretKey
     * @return void
     */
    public function __construct($apiUrl, $publicKey, $secretKey)
    {
        $this->apiUrl = $apiUrl;
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
    }

    /**
     * @param  string  $version
     * @return  \App\Libraries\Xendit\Account
     */
    public function accounts(string $version = 'v2')
    {
        return new Account(
            join('/', [$this->apiUrl, $version, 'accounts']),
            $this->publicKey,
            $this->secretKey
        );
    }

    /**
     * @return \App\Libraries\Xendit\CallbackUrl
     */
    public function callbackUrls()
    {
        return new CallbackUrl(
            join('/', [$this->apiUrl, 'callback_urls']),
            $this->publicKey,
            $this->secretKey
        );
    }

    /**
     * @return \App\Libraries\Xendit\EWalletCharge
     */
    public function eWalletCharges()
    {
        return new EWalletCharge(
            join('/', [$this->apiUrl, 'ewallets']),
            $this->publicKey,
            $this->secretKey
        );
    }

    /**
     * @return \App\Libraries\Xendit\FeeRule
     */
    public function feeRules()
    {
        return new FeeRule(
            $this->apiUrl,
            $this->publicKey,
            $this->secretKey
        );
    }

    /**
     * Create a new Xendit client.
     *
     * @param  bool  $secret
     * @param  string|null  $userId
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client(bool $secret = true, ?string $userId = null)
    {
        return Http::asJson()
            ->baseUrl($this->apiUrl)
            ->withToken($this->getBasicToken($secret), 'Basic')
            ->when($userId, function ($request, $userId) {
                $request->withHeaders(['For-User-Id' => $userId]);
            });
    }

    /**
     * Get the basic auth token for Xendit requests.
     *
     * @param  bool  $secret
     * @return string
     */
    protected function getBasicToken($secret = true)
    {
        $key = $secret ? $this->secretKey : $this->publicKey;

        return base64_encode("{$key}:");
    }
}
