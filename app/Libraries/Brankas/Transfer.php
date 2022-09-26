<?php

namespace App\Libraries\Brankas;

use App\Facades\Brankas;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;

class Transfer
{
    /**
     * Find the transfer with the given transaction ID.
     *
     * @param  string  $transactionId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function find($transactionId)
    {
        try {
            $response = Brankas::transfer()
                ->get('transfer', [
                    'query' => ['transfer_ids' => $transactionId],
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
