<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Arr;

class PaymentToken
{
    const VISA = [
        [
            'number' => '4012001037141112',
            'expMonth' => '12',
            'expYear' => '2027',
            'cvc' => '212',
        ],
        [
            'number' => '4123450131001522',
            'expMonth' => '12',
            'expYear' => '2025',
            'cvc' => '123',
            'passkey' => true,
        ],
        [
            'number' => '4444333322221111',
            'expMonth' => '12',
            'expYear' => '2025',
            'cvc' => '888',
            'passkey' => true,
            'success' => false,
        ],
    ];

    /**
     * Create a payment token for testing purposes.
     *
     * @param  string  $type
     * @param  array  $card
     * @return array
     */
    public static function create($type = 'visa', $withPasskey = false, $success = true)
    {
        $card = collect(constant(static::class . '::' . mb_strtoupper($type)))
            ->filter(function ($card) use ($withPasskey, $success) {
                return ($card['passkey'] ?? false) === $withPasskey
                    && ($card['success'] ?? true) === $success;
            })
            ->first();

        if (Arr::has($card, 'number')) {
            $card['number'] = preg_replace('/\D/', '', $card['number']);
        }

        try {
            $response = PayMaya::payments()
                ->post('payment-tokens', ['json' => compact('card')]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
