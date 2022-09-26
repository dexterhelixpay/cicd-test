<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

class MerchantSetProductPriceNotification extends SmsNotification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The order billing date.
     *
     * @var string
     */
    public $billingDate;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($merchant, $billingDate)
    {
        $this->merchant = $merchant;
        $this->billingDate = Carbon::parse($billingDate)->format('F j, Y');
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

        return (new MailMessage)
            ->subject('HelixPay - Set product price reminder')
            ->greeting("Good day {$this->merchant->name}!")
            ->line("This is a gentle reminder that you need to set an amount for the billing date on {$this->billingDate}.")
            ->markdown('vendor.notifications.email');
    }

    /**
     * {@inheritdoc}
     */
    public function toSms($notifiable)
    {
        if (!$this->merchant->mobile_number) return;

        return (new SmsMessage)
            ->line(
                "Good day {$this->merchant->name}!!"
                . "\n\nThis is a gentle reminder that you need to set an amount for the billing date on {$this->billingDate}."
                . "\n\nThank you,"
                . "HelixPay Team"
            );
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
