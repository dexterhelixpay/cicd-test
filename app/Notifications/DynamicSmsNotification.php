<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class DynamicSmsNotification extends SmsNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The body
     *
     * @var string
     */
    public $body;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($body)
    {
        $this->body = $body;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toSms($notifiable)
    {
        return (new SmsMessage)->line($this->body);
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
