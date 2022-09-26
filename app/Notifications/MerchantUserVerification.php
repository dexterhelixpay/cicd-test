<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Vinkla\Hashids\Facades\Hashids;

class MerchantUserVerification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The verification token.
     *
     * @var string
     */
    public $token;

    /**
     * Flag if it is for verifying updated email.
     *
     * @var bool
     */
    public $forUpdate;

    /**
     * Create a new notification instance.
     *
     * @param  string  $token
     * @param  bool  $forUpdate
     * @return void
     */
    public function __construct($token, $forUpdate = false)
    {
        $this->token = $token;
        $this->forUpdate = $forUpdate;

        $this->afterCommit = true;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if (env('APP_ENV') == 'dev') {
            return ['sendgrid'];
        }
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
        if ($this->forUpdate) {
            $notifiable->email = $notifiable->new_email;
        }

        $key = Hashids::connection('merchant_user')->encode($notifiable->getKey());
        $token = "{$key}.{$this->token}";

        $url = config('bukopay.url.merchant_console') . "/#/create-account/verify/{$token}";

        return (new MailMessage)
            ->subject('HelixPay - Email Verification')
            ->greeting('Hello!')
            ->when($this->forUpdate, function (MailMessage $mail) use ($url, $notifiable) {
                return $mail
                    ->line("You're receiving this email because your account's email has been updated on our merchant console.")
                    ->line('Click the button below to verify your email.')
                    ->line("Username: {$notifiable->username}")
                    ->action('Verify Email', $url);
            }, function (MailMessage $mail) use ($url, $notifiable) {
                return $mail
                    ->line("You're receiving this email because an account was created for you on our merchant console.")
                    ->line('Click the button below to verify your account.')
                    ->line("Username: {$notifiable->username}")
                    ->action('Verify Account', $url);
            })
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
