<?php

namespace App\Libraries;

use Http;

class PesoPay
{
    /**
     * The PayMaya API URL.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The merchant ID.
     *
     * @var int
     */
    protected $merchantId;

    /**
     * The secure hash secret key.
     *
     * @var string
     */
    protected $secureHashSecret;

    /**
     * Create a new PesoPay instance.
     *
     * @param  string  $apiUrl
     * @param  int  $merchantId
     * @param  string  $secureHashSecret
     * @return void
     */
    public function __construct($apiUrl, $merchantId, $secureHashSecret)
    {
        $this->apiUrl = $apiUrl;
        $this->merchantId = $merchantId;
        $this->secureHashSecret = $secureHashSecret;
    }

    /**
     * Create a new PesoPay HTTP client.
     *
     * @param  string|null  $prefix
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function client($prefix = null)
    {
        $prefix = ltrim(trim($prefix ?: ''), '/');
        $baseUrl = $prefix ? "{$this->apiUrl}/{$prefix}" : $this->apiUrl;

        return Http::asForm()->baseUrl($baseUrl);
    }

    /**
     * Get the merchant ID.
     *
     * @return int
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * Generate a secure hash.
     *
     * @param  string   $reference
     * @param  string  $currency
     * @param  float  $amount
     * @param  string  $paymentType
     * @return string
     */
    public function generateSecureHash($reference, $currency, $amount, $paymentType)
    {
        $signingData = join('|', [
            (string) $this->merchantId,
            (string) $reference,
            $currency,
            (string) $amount,
            $paymentType,
            $this->secureHashSecret
        ]);

        return sha1($signingData);
    }

    /**
     * Verify the data feed with the secure hash.
     *
     * @param  array   $data
     * @param  string  $secureHash
     * @return bool
     */
    public function verifyDatafeed($data, $secureHash)
    {
        $dataString = join('|', [
            $data['src'] ?? '',
            $data['prc'] ?? '',
            $data['successcode'] ?? '',
            $data['Ref'] ?? '',
            $data['PayRef'] ?? '',
            $data['Cur'] ?? '',
            $data['Amt'] ?? '',
            $data['payerAuth'] ?? '',
            $this->secureHashSecret
        ]);

        return sha1($dataString) === $secureHash;
    }
}
