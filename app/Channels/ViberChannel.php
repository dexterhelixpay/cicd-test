<?php

namespace App\Channels;

use App\Facades\Viber;
use App\Support\Model as ModelHelper;
use Illuminate\Database\Eloquent\Model;
use App\Libraries\Viber\Message as ViberMessage;

class ViberChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \App\Notifications\Contracts\SendsViber  $notification
     * @return void
     */
    public function send($notifiable, $notification)
    {
        if (!$viberId = $notifiable->routeNotificationFor('viber', $notification)) {
            return;
        }

        if (!$message = (string) $notification->toViber($notifiable)) {
            return;
        }

        if (
            $notifiable instanceof Model
            && ModelHelper::hasRelation($notifiable, 'merchant')
            && ($merchant = $notifiable->merchant()->first())
        ) {
            if ($merchant->viber_key) {
                return Viber::withToken(
                    $merchant->viber_key,
                    function () use($viberId, $message) {
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

        ViberMessage::send(
            $viberId,
            $message
        )->then(null, function ($e) {
            throw $e;
        })->wait(false);
    }
}
