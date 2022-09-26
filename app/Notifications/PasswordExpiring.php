<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
     * @param  \App\Models\User|\App\Models\MerchantUser  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $expiresOn = Carbon::parse($notifiable->password_updated_at)
            ->addDays(setting('PasswordMaxAge', 90))
            ->startOfDay()
            ->format('F j, Y');

        return (new MailMessage)
            ->subject('HelixPay - Password Expiration')
            ->greeting('Hello!')
            ->line("You're receiving this email because your password will expire on {$expiresOn}.")
            ->line('Please update your password to prevent locking yourself out.')
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
