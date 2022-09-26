<?php

namespace App\Libraries\PayMongo;

use App\Facades\PayMongo;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\URL;

class Source
{
    /**
     * Create a payment source.
     *
     * @param  string  $type
     * @param  float  $amount
     * @param  array  $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function create($type, $amount, $parameters = [])
    {
        $successUrl = URL::signedRoute(
            'api.v1.payments.paymongo.redirect',
            array_merge([
                'type' => 'charged',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.paymongo.redirect',
            array_merge([
                'type' => 'charged',
                'success' => 0,
            ], $parameters)
        );

        try {
            $response = PayMongo::client()
                ->post('sources', [
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'type' => $type,
                                'amount' => intval($amount * 100),
                                'currency' => 'PHP',
                                'redirect' => [
                                    'success' => $successUrl,
                                    'failed' => $failedUrl,
                                ],
                            ],
                        ],
                    ],
                ]);

            return new FulfilledPromise(
                json_decode($response->getBody(), true)['data']
            );
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
