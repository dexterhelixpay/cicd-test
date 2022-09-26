<?php

namespace App\Libraries\Brankas;

use App\Facades\Brankas;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\URL;

class Direct
{
    /**
     * Create a payment transaction using an account.
     *
     * @param  float  $amount
     * @param  string  $referenceNumber
     * @param  array  $parameters
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function checkout($amount, $referenceNumber, $parameters = [])
    {
        $successUrl = URL::signedRoute(
            'api.v1.payments.brankas.redirect',
            [
                'type' => 'transferred',
                'success' => 1,
                'order' => $parameters['order'],
                'isFromFailedPayment' => $parameters['isFromFailedPayment']
            ]
        );

        $failedUrl = URL::signedRoute(
            'api.v1.payments.brankas.redirect',
            [
                'type' => 'transferred',
                'success' => 0,
                'order' => $parameters['order'],
                'isFromFailedPayment' => $parameters['isFromFailedPayment']
            ]
        );

        try {
            $response = Brankas::checkout()
                ->post('checkout', [
                    'json' => [
                        'from' => [
                            'type' => 'BANK',
                            'bank_code' => app()->isProduction()
                                ? $parameters['bankCode']
                                : 'DUMMY_BANK_PERSONAL',
                            'country' => 'PH'
                        ],
                        'reference_id' => $referenceNumber,
                        'destination_account_id' => config('services.brankas.destination_account_id'),
                        'amount'=> [
                            'cur'=> 'PHP',
                            'num' => strval(intval($amount * 100)),
                        ],
                        'customer'=> [
                       'fname' => 'HelixPay',
                            'mname' => 'Payment',
                            'lname' => $parameters['customer']['name'],
                            'email' => $parameters['customer']['email'],
                            'mobile' => $parameters['customer']['mobile_number'],
                            'customer_id' => strval($parameters['customer']['id'])
                        ],
                        'memo' => "CSTMR{$parameters['customer']['id']}",
                        'payment_channel' => '_',
                        'client' => [
                            'display_name' => $parameters['merchantName'],
                            'logo_url' => strval($parameters['logo_url']),
                            'return_url' => $successUrl,
                            'fail_url' => $failedUrl,
                            'deep_link' => true
                        ]
                    ]
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Get the Brankas transfer record.
     *
     * @param  string  $transferId
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function get($transferId)
    {
        try {
            $response = Brankas::transfer()
                ->get('transfer', [
                    'query' => ['transfer_ids' => $transferId],
                ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
