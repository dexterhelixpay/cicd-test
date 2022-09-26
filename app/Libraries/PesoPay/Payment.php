<?php

namespace App\Libraries\PesoPay;

use App\Facades\PesoPay;
use Illuminate\Support\Facades\URL;

class Payment
{
    /**
     * Constant representing a normal payment.
     *
     * @var string
     */
    const NORMAL = 'N';

    /**
     * Constant representing a GCash payment.
     *
     * @var string
     */
    const GCASH = 'GCash';

    /**
     * Constant representing a GrabPay payment.
     *
     * @var string
     */
    const GRABPAY = 'GRABPAY';

    /**
     * @param  string  $method
     * @param  float  $amount
     * @param  string  $reference
     * @param  array  $parameters
     * @return \Illuminate\Http\Client\Response
     */
    public static function pay($method, $amount, $reference, $parameters = [])
    {
        $successUrl = URL::signedRoute(
            'api.v1.payments.pesopay.redirect',
            array_merge([
                'type' => 'payment',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.pesopay.redirect',
            array_merge([
                'type' => 'payment',
                'success' => 0,
            ], $parameters)
        );

        $data = array_merge([
            'amount' => $amount,
            'orderRef' => (string) $reference,
            'successUrl' => $successUrl,
            'failUrl' => $failedUrl,
            'cancelUrl' => $failedUrl,
            'payMethod' => $method,
        ], [
            'payType' => 'N',
            'redirect' => 0,
            'currCode' => PesoPay::CURRENCY_PHP,
            'lang' => PesoPay::LANG_ENGLISH,
            'merchantId' => PesoPay::getMerchantId(),
            'secureHash' => PesoPay::generateSecureHash(
                $reference,
                PesoPay::CURRENCY_PHP,
                $amount,
                static::NORMAL
            ),
        ]);

        return PesoPay::client('payment')->post('payForm.jsp', $data);
    }
}
