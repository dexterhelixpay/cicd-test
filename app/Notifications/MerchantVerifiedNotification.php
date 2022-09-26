<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class MerchantVerifiedNotification extends SmsNotification implements SendsMail, ShouldQueue
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
        return array_merge(parent::via($notifiable), ['sendgrid']);
    }

    /**
     * {@inheritdoc}
     */
    public function toMail($notifiable)
    {
        if (!$this->merchant->email) return;

        $console = config('bukopay.url.merchant_console') . '/#/login';

        return (new MailMessage)
            ->subject('HelixPay - Verified')
            ->greeting(' Congratulations!')
            ->line("Your account {$this->merchant->name} has been verified.")
            ->line("Here is the link to your merchant account {$console}")
            ->markdown('vendor.notifications.email');
    }

    /**
     * {@inheritdoc}
     */
    public function toSms($notifiable)
    {
        if ($this->merchant->mobile_number) {
            $console = config('bukopay.url.merchant_console') . '/#/login';

            return (new SmsMessage)
                ->line(
                    "Congratulations!"
                    . "\n\nYour account {$this->merchant->name} has been verified."
                    . "\nHere is the link to your merchant account {$console}"
                    . "\n\nThank you,"
                    . "\nHelixPay Team"
                );
        }
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
