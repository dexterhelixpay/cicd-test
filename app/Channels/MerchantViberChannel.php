<?php

namespace App\Channels;

use App\Facades\Viber;
use App\Support\Model as ModelHelper;
use Illuminate\Database\Eloquent\Model;
use App\Libraries\Viber\Message as ViberMessage;

class MerchantViberChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsMerchantViber  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (!$viberId = $notifiable->routeNotificationFor('viber', $notification)) {
            return;
        }

        if (!$message = (string) $notification->toMerchantViber($notifiable)) {
            return;
        }

        Viber::withToken(
            config('services.viber.merchant_auth_token'),
            function () use ($viberId, $message) {
                ViberMessage::send(
                    $viberId,
                    $message
                )->then(null, function ($e) {
                    throw $e;
                })->wait(false);
            }
        );
    }
}
