<?php

namespace App\Libraries\Viber;

use App\Facades\Viber;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;

class Webhook
{
      /**
     * Get the Brankas transfer record.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function setup($url)
    {
        try {
            $response = Viber::webhook()->post("set_webhook", [
                'json' => [
                    'url' => $url,
                    'event_types' => [
                       'delivered',
                       'seen',
                       'failed',
                       'subscribed',
                       'unsubscribed',
                       'conversation_started'
                    ],
                    'send_name' => true,
                    'send_photo' => true
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }

    /**
     * Get the Brankas transfer record.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function remove()
    {
        try {
            $response = Viber::webhook()->post("set_webhook", [
                'json' => [
                    'url' => '',
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
