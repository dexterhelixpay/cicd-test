<?php

namespace App\Libraries\PayMaya;

class Response
{
    const CARD_NOT_FOUND = 'PY0027';
    const CUSTOMER_NOT_FOUND = 'PY0023';
    const PAYMENT_EXPIRED = 'PAYMENT_EXPIRED';
    const PAYMENT_FAILED = 'PAYMENT_FAILED';
    const PAYMENT_SUCCESS = 'PAYMENT_SUCCESS';
    const FOR_AUTHENTICATION = 'FOR_AUTHENTICATION';

    /**
     * Get the error code.
     *
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return string|null
     */
    public static function getCode($response)
    {
        $body = json_decode($response->getBody(), true);

        return data_get($body, 'code');
    }
}
