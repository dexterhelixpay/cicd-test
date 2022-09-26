<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class MerchantPasswordReset extends SmsNotification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The password reset code.
     *
     * @var string
     */
    public $code;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code
     * @return void
     */
    public function __construct($code)
    {
        $this->code = $code;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return array_merge(parent::via($notifiable), ['sendgrid']);
    }

    /**
     * {@inheritdoc}
     */
    public function toMail($notifiable)
    {
        $expire = config('auth.passwords.merchant_users.expire');

        return (new MailMessage)
            ->subject('HelixPay - Password Reset')
            ->greeting('Hello!')
            ->line("You're receiving this email because we received a password reset request from your account.")
            ->line("Use the code below to reset your password. The code is only valid for {$expire} minute/s.")
            ->line(new HtmlString('<strong style="color:black !important;">' . $this->code . '</strong>'))
            ->line('If you did not request a password reset, no further action is required.')
            ->markdown('vendor.notifications.email');
    }

    /**
     * {@inheritdoc}
     */
    public function toSms($notifiable)
    {
        return (new SmsMessage)
            ->line("Use {$this->code} as your HelixPay password reset code.");
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
