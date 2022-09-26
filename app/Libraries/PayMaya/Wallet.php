<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class Wallet
{
   /**
     * The link ID.
     *
     * @var string
     */
    protected $linkId;

    /**
     * The request referene number
     *
     * @var string
     */
    protected $referenceNumber;

    /**
     * Create a new PayMaya wallet instance.
     *
     * @param  string  $customerId
     * @param  string  $linkId
     * @return void
     */
    public function __construct($referenceNumber, $linkId = null)
    {
        $this->linkId = $linkId;
        $this->referenceNumber = $referenceNumber;
    }

    /**
     * Find the wallet with the given link ID.
     *
     * @param  string  $linkId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function find($linkId)
    {
        try {
            $response =  PayMaya::payBy(true)
                ->get("link/{$linkId}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Link the given card to the customer.
     *
     * @param  string  $customerId
     * @param  string  $subscriptionId
     * @param  array  $metadata
     * @param  array  $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function link(
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
                'type' => 'wallet_verification',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'wallet_verification',
                'success' => 0,
            ], $parameters)
        );

        try {
            $response = PayMaya::payBy(false)
                ->post('link', [
                    'json' => [
                        'requestReferenceNumber' => $this->referenceNumber,
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
     * @param  string  $subscriptionId
     * @param  array  $metadata
     * @param  array  $parameters
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function pay(
        $amount,
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
                'type' => 'wallet_payment',
                'success' => 1,
            ], $parameters)
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.paymaya.redirect',
            array_merge([
                'type' => 'wallet_payment',
                'success' => 0,
            ], $parameters)
        );

        try {
            $response = PayMaya::payBy(true)
                ->post("link/{$this->linkId}/execute", [
                    'json' => [
                        'totalAmount' => [
                            'currency' => 'PHP',
                            'value' => $amount
                        ],
                        'requestReferenceNumber' => $this->referenceNumber,
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
}
