<?php

namespace App\Notifications\Contracts;

interface SendsMail
{
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage|\SendGrid\Mail\Mail|null
     */
    public function toMail($notifiable);
}
