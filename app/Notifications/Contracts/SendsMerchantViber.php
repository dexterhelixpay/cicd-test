<?php

namespace App\Notifications\Contracts;

interface SendsMerchantViber
{
    /**
     * Get the SMS representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \App\Channels\Messages\SmsMessage|string|null
     */
    public function toMerchantViber($notifiable);
}
