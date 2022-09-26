<?php

namespace App\Notifications;

use App\Notifications\Contracts\SendsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Vinkla\Hashids\Facades\Hashids;

class UserVerification extends Notification implements SendsMail, ShouldQueue
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
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['sendgrid'];
    }

    /**
     * {@inheritdoc}
     */
    public function toMail($notifiable)
    {
        $key = Hashids::connection('user')->encode($notifiable->getKey());
        $token = "{$key}.{$this->token}";
        $query = http_build_query(['token' => $token]);
        $url = config('bukopay.url.control_panel') . "/#/account-verify?{$query}";

        return (new MailMessage)
            ->subject('Admin Account Verification')
            ->greeting('Hello!')
            ->line("You are receiving this email because we created you an admin account.")
            ->line('Click the button below to verify your account.')
            ->line("Email: {$notifiable->email}")
            ->action('Verify Account', $url)
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
