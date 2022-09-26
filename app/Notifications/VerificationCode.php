<?php

namespace App\Notifications;

use App\Messages\SmsMessage;
use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsConfidentialSms;
use App\Notifications\Contracts\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

class VerificationCode extends SmsNotification implements Cacheable, SendsConfidentialSms
{
    use Queueable;

    /**
     * The verification code.
     *
     * @var string
     */
    public $code;

    /**
     * Flag if it is for verifying updated email.
     *
     * @var bool
     */
    public $forUpdate;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code
     * @return void
     */
    public function __construct($code, $forUpdate = false)
    {
        $this->code = $code;
        $this->forUpdate = $forUpdate;
    }

    /**
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        $mobileNumber = $notifiable->routeNotificationFor('sms');

        if ($this->forUpdate && $notifiable->new_mobile_number) {
            $mobileNumber = $notifiable->new_mobile_number;
        }

        return [
            "verification_code." . mobile_number($mobileNumber),
            class_basename($this) . ':' . mobile_number($mobileNumber),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function toEncryptedSms($notifiable)
    {
        if ($this->forUpdate && $notifiable->new_mobile_number) {
            $notifiable->mobile_number = $notifiable->new_mobile_number;
        }

        $encryptedCode = base64_encode(strtoupper($this->code));

        return (new SmsMessage)
            ->line('Use ' .  $encryptedCode . ' as your HelixPay.ph verification code');
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
            ->line("Use the code below as your verification code.")
            ->line(new HtmlString('<strong style="color: black !important;">' . strtoupper($this->code) . '</strong>'))
            ->line('If you did not request this, no further action is required.');
    }

    /**
     * {@inheritdoc}
     */
    public function toSms($notifiable)
    {
        if ($this->forUpdate && $notifiable->new_mobile_number) {
            $notifiable->mobile_number = $notifiable->new_mobile_number;
        }

        return (new SmsMessage)
            ->line('Use ' . strtoupper($this->code) . ' as your HelixPay.ph verification code');
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
            'code' => strtoupper($this->code),
            'message' => (string) $this->toSms($notifiable),

            // Deprecated
            'Code' => strtoupper($this->code),
        ];
    }
}
