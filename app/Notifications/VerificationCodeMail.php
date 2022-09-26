<?php

namespace App\Notifications;

use App\Notifications\Contracts\SendsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class VerificationCodeMail extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The password reset code.
     *
     * @var string
     */
    public $code;

    /**
     * The type
     *
     * @var string
     */
    public $type;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code
     * @return void
     */
    public function __construct($code, $type)
    {
        $this->code = $code;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return app()->isLocal() ? ['mail'] : ['sendgrid'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('HelixPay - OTP')
            ->greeting('Hello!')
            ->line("You're receiving this email because we received a {$this->type} request from your account.")
            ->line("Use the code below to confirm your {$this->type}")
            ->line(new HtmlString('<strong style="color:black !important;">' . $this->code . '</strong>'))
            ->line('If you did not request, no further action is required.')
            ->markdown('vendor.notifications.email');
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
