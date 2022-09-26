<?php

namespace App\Libraries\PayMongo;

use App\Facades\PayMongo;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;

class Payment
{
    /**
     * Create a payment transaction from the given source.
     *
     * @param  string  $sourceId
     * @param  float  $amount
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function create($sourceId, $amount)
    {
        try {
            $response = PayMongo::client()
                ->post('payments', [
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'source' => [
                                    'id' => $sourceId,
                                    'type' => 'source',
                                ],
                                'amount' => intval($amount * 100),
                                'currency' => 'PHP',
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
