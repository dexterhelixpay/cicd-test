<?php

namespace App\Libraries\PayMaya;

use App\Facades\PayMaya;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;

class Payment
{
    /**
     * Constant representing an expired payment.
     *
     * @var string
     */
    const PAYMENT_EXPIRED = 'PAYMENT_EXPIRED';

    /**
     * Constant representing an failed payment.
     *
     * @var string
     */
    const PAYMENT_FAILED = 'PAYMENT_FAILED';

    /**
     * Constant representing an successful payment.
     *
     * @var string
     */
    const PAYMENT_SUCCESS = 'PAYMENT_SUCCESS';

    /**
     * The payment ID.
     *
     * @var string
     */
    protected $id;

    /**
     * Create a new PayMaya payment instance.
     *
     * @param  string  $id
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Create a payment transaction using an account.
     *
     * @param  float  $amount
     * @param  string  $referenceNumber
     * @param  array  $signature
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function createForAccount($amount, $referenceNumber, $signature = [])
    {
        $successUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt(array_merge([
                'type' => 'wallet_payment',
                'success' => true,
                'reference_number' => $referenceNumber,
            ], $signature))),
        ]);

        $failedUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt(array_merge([
                'type' => 'wallet_payment',
                'success' => false,
                'reference_number' => $referenceNumber,
            ], $signature))),
        ]);

        try {
            $response = PayMaya::payBy()
                ->post('payments', [
                    'json' => [
                        'totalAmount' => [
                            'currency' => 'PHP',
                            'value' => $amount,
                        ],
                        'redirectUrl' => [
                            'success' => $successUrl,
                            'failure' => $failedUrl,
                            'cancel' => $failedUrl,
                        ],
                        'requestReferenceNumber' => $referenceNumber,
                    ],
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Create a payment transaction from the given token.
     *
     * @param  string  $paymentTokenId
     * @param  float  $amount
     * @param  array  $signature
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function createFromToken($paymentTokenId, $amount, $signature = [])
    {
        $successUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt(array_merge([
                'type' => 'card_payment',
                'success' => true,
            ], $signature))),
        ]);

        $failedUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt(array_merge([
                'type' => 'card_payment',
                'success' => false,
            ], $signature))),
        ]);

        try {
            $response = PayMaya::payments(true)
                ->post('payments', [
                    'json' => [
                        'paymentTokenId' => $paymentTokenId,
                        'totalAmount' => [
                            'currency' =>'PHP',
                            'amount' => $amount,
                        ],
                        'redirectUrl' => [
                            'success' => $successUrl,
                            'failure' => $failedUrl,
                            'cancel' => $failedUrl,
                        ]
                    ]
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Get the PayMaya payment record.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function get()
    {
        try {
            $response = PayMaya::payments(true)->get("payments/{$this->id}");

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * One time payment.
     *
     * @param  float  $amount
     * @param  mixed  $jobId
     * @param  bool  $isFromPublic
     * @return array
     */
    public function pay($amount, $jobId, $isFromPublic = false)
    {
        $successUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt([
                'type' => 'card_verification',
                'job_id' => $jobId,
                'success' => true,
                'is_from_public' => $isFromPublic
            ])),
        ]);

        $failedUrl = route('api.v1.paymaya.redirect', [
            'signature' => urlencode(encrypt([
                'type' => 'card_verification',
                'job_id' => $jobId,
                'success' => false,
                'is_from_public' => $isFromPublic
            ])),
        ]);

        $response = PayMaya::payments(true)
            ->post('payments', [
                'json' => [
                    'paymentTokenId' => $this->id,
                    'totalAmount' => [
                        'amount' => $amount,
                        'currency' =>'PHP',
                    ],
                    'redirectUrl' => [
                        'success' => $successUrl,
                        'failure' => $failedUrl,
                        'cancel' => $failedUrl,
                    ]
                ]
            ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Refund the payment.
     *
     * @param  float  $amount
     * @param  string $reason
     * @return array
     */
    public function refund($amount, $reason)
    {
        $response = PayMaya::payments(true)
            ->post("payments/{$this->id}/refunds", [
                'json' => [
                    'totalAmount' => [
                        'amount' => $amount,
                        'currency' => 'PHP',
                    ],
                    'reason' => $reason,
                ],
            ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * List all refund transactions.
     *
     * @return array
     */
    public function refunds()
    {
        $response = PayMaya::payments(true)->get("payments/{$this->id}/refunds");

        return json_decode($response->getBody(), true);
    }

    /**
     * Void the payment.
     *
     * @param  string  $reason
     * @return array
     */
    public function void($reason)
    {
        $response = PayMaya::payments(true)
            ->post("payments/{$this->id}/voids", [
                'json' => compact('reason'),
            ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * List all void transactions.
     *
     * @return array
     */
    public function voids()
    {
        $response = PayMaya::payments(true)->get("payments/{$this->id}/voids");

        return json_decode($response->getBody(), true);
    }
}
