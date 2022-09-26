<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Notification;

class MerchantMaxAmountLimitNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The subscription model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code
     * @return void
     */
    public function __construct($merchant)
    {
        $this->merchant = $merchant;
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
        if (!$this->merchant->email) return;

        return (new MailMessage)
            ->subject('HelixPay - Limit Reached')
            ->greeting('Hello!')
            ->line("Your account reached the maximum amount transaction limit and is being reviewed by the HelixPay Team.")
            ->line("New {$this->merchant->subscription_term_plural} will not be allowed until the review is complete.")
            ->line(new HtmlString("Please contact <a href='https://m.me/HelixPay.ph'>HelixPay</a> if you have any questions."))
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
