<?php

namespace App\Notifications\Contracts;

interface SendsConfidentialSms extends SendsSms
{
    /**
     * Get the encrypted SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Channels\Messages\SmsMessage|string
     */
    public function toEncryptedSms($notifiable);
}
