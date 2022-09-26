<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class Card
{
    /**
     * The card ID.
     *
     * @var string
     */
    protected $cardId;

    /**
     * The customer ID.
     *
     * @var string
     */
    protected $customerId;

    /**
     * Create a new PayMaya card instance.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @return void
     */
    public function __construct($customerId, $cardId)
    {
        $this->customerId = $customerId;
        $this->cardId = $cardId;
    }

    /**
     * Pay using the card.
     *
     * @param  float  $amount
     * @param  array  $metadata
     * @param  array  $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function pay($amount, $metadata = [], $parameters = [])
    {
        $metadata = array_merge($metadata, [
            'mci' => config('services.paymaya.metadata.mci'),
            'mco' => config('services.paymaya.metadata.mco'),
            'mpc' => config('services.paymaya.metadata.mpc'),
        ]);

        $successUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'card_payment',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'card_payment',
                'success' => 0,
            ], $parameters)
        );

        try {
            $response = PayMaya::payments(true)
                ->post("customers/{$this->customerId}/cards/{$this->cardId}/payments", [
                    'json' => [
                        'totalAmount' => [
                            'amount' => $amount,
                            'currency' => 'PHP',
                        ],
                        'metadata' => ['pf' => $metadata],
                        'redirectUrl' => [
                            'success' => $successUrl,
                            'failure' => $failedUrl,
                            'cancel' => $failedUrl,
                        ],
                    ],
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Link the given card to the customer.
     *
     * @param  string  $customerId
     * @param  string  $cardTokenId
     * @param  bool  $isDefault
     * @param  array  $parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function link(
        $customerId,
        $cardTokenId,
        $isDefault = false,
        $metadata = [],
        $parameters = []
    ) {
        $metadata = array_merge($metadata, [
            'mci' => config('services.paymaya.metadata.mci'),
            'mco' => config('services.paymaya.metadata.mco'),
            'mpc' => config('services.paymaya.metadata.mpc'),
        ]);

        $successUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'card_verification',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'card_verification',
                'success' => 0,
            ], $parameters)
        );

        try {
            $response = PayMaya::payments(true)
                ->post("customers/{$customerId}/cards", [
                    'json' => [
                        'paymentTokenId' => $cardTokenId,
                        'isDefault' => $isDefault,
                        'metadata' => ['pf' => $metadata],
                        'redirectUrl' => [
                            'success' => $successUrl,
                            'failure' => $failedUrl,
                            'cancel' => $failedUrl,
                        ],
                    ],
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Create a customer using the given data.
     *
     * @param  array  $data
     * @return array
     */
    public static function create(string $customerId, array $data)
    {
        if (!Arr::has($data, 'redirectUrl')) {
            $success = route('api.v1.paymaya.redirect', [
                'signature' => urlencode(encrypt([
                    'type' => 'card_verification',
                    'success' => true,
                    'card_token_id' => $data['paymentTokenId'],
                ])),
            ]);

            $error = route('api.v1.paymaya.redirect', [
                'signature' => urlencode(encrypt([
                    'type' => 'card_verification',
                    'success' => false,
                    'card_token_id' => $data['paymentTokenId'],
                ])),
            ]);

            $data['redirectUrl'] = [
                'success' => $success,
                'failure' => $error,
                'cancel' => $error,
            ];
        }

        $response = PayMaya::payments(true)
            ->post("customers/{$customerId}/cards", ['json' => $data]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Delete the given card of the customer.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @return array
     */
    public static function delete(string $customerId, string $cardId)
    {
        $response = PayMaya::payments(true)
            ->delete("customers/{$customerId}/cards/{$cardId}");

        return json_decode($response->getBody(), true);
    }

    /**
     * Find the customer's card with the given ID.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @return array|null
     */
    public static function find(string $customerId, string $cardId)
    {
        try {
            $response = PayMaya::payments(true)
                ->get("customers/{$customerId}/cards/{$cardId}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Get all the customer's cards.
     *
     * @param  string  $customerId
     * @return array
     */
    public static function get(string $customerId)
    {
        $response = PayMaya::payments(true)
            ->get("customers/{$customerId}/cards");

        return json_decode($response->getBody(), true);
    }

    /**
     * Update the customer's card.
     *
     * @param  string  $customerId
     * @param  string  $cardId
     * @param  array  $data
     * @return array|null
     */
    public static function update(string $customerId, string $cardId, array $data)
    {
        $response = PayMaya::payments(true)
            ->put("customers/{$customerId}/cards/{$cardId}", [
                'json' => Arr::only($data, 'isDefault'),
            ]);

        return json_decode($response->getBody(), true);
    }
}
