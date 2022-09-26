<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class VoucherVerificationMail extends Notification
{
    use Queueable;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

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
     * @param  \App\Models\Merchant  $merchant
     *
     * @return void
     */
    public function __construct($code, $merchant)
    {
        $this->code = $code;
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
        $profileUrl = config('bukopay.url.profile');

        return (new MailMessage)
            ->subject('HelixPay - OTP')
            ->greeting('Hello!')
            ->line("You're receiving this email because we received an exclusive voucher confirmation from your account.")
            ->line("Use the code below to apply the voucher:")
            ->line(new HtmlString('<strong style="color:black !important;">' . $this->code . '</strong>'))
            ->line('If you did not request, no further action is required.')
            ->markdown('emails.voucher-verification', [
                'headerUrl' => "https://{$this->merchant->subdomain}.{$profileUrl}",
                'headerText' => $this->merchant->name,
                'headerImage' => $this->merchant->is_logo_visible ? $this->merchant->logo_image_path : null
            ]);
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
