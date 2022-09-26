<?php

namespace App\Libraries\PayMaya\v2;

class Payment extends Api
{
    /**
     * Constant representing an expired payment.
     *
     * @var string
     */
    const EXPIRED = 'PAYMENT_EXPIRED';

    /**
     * Constant representing an failed payment.
     *
     * @var string
     */
    const FAILED = 'PAYMENT_FAILED';

    /**
     * Constant representing an successful payment.
     *
     * @var string
     */
    const SUCCESS = 'PAYMENT_SUCCESS';

    /**
     * Find the payment with the given ID.
     *
     * @param  string  $id
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function find(string $id, string $secretKey)
    {
        return $this->client($secretKey)->get("payments/{$id}");
    }

    /**
     * Get a list of refund transactions of the given payment.
     *
     * @param  string  $id
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function getRefunds(string $id, string $secretKey)
    {
        return $this->client($secretKey)->get("payments/{$id}/refunds");
    }

    /**
     * Get a list of void transactions of the given payment.
     *
     * @param  string  $id
     * @param  string  $secretKey
     * @return \Illuminate\Http\Client\Response
     */
    public function getVoids(string $id, string $secretKey)
    {
        return $this->client($secretKey)->get("payments/{$id}/voids");
    }
}
