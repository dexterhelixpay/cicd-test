<?php

namespace App\Libraries\Viber;

use App\Facades\Viber;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class Message
{
      /**
     * Get the Brankas transfer record.
     *
     * @param  string  $receiverId
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function send($receiverId, $message)
    {
        try {
            $response = Viber::message()->post("send_message", [
                'json' => [
                    'receiver' => $receiverId,
                    'min_api_version' => 1,
                    'sender' => Viber::getSender(),
                    'tracking_data' => 'tracking data',
                    'type' => 'text',
                    'text' => $message,
                ]
            ]);

            return new FulfilledPromise(json_decode($response->getBody(), true));
        } catch (BadResponseException $e) {
            return new RejectedPromise($e);
        }
    }
}
