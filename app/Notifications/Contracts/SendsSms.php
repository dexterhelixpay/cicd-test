<?php

namespace App\Notifications\Contracts;

interface SendsSms
{
    /**
     * Get the SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Channels\Messages\SmsMessage|string|null
     */
    public function toSms($notifiable);
}
