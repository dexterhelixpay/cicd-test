<?php

namespace App\Notifications;

use App\Mail\EmailBlast;
use App\Models\EmailEvent;
use App\Models\MerchantEmailBlast;
use App\Notifications\Contracts\SendsMail;
use App\Traits\TracksEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailBlastNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable, TracksEmail;

    /**
     * The email blast model.
     *
     * @var \App\Models\MerchantEmailBlast
     */
    public $emailBlast;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;


    /**
     * The merchant model.
     *
     * @var \App\Models\Customer
     */
    public $customer;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($emailBlast, $merchant, $customer)
    {
        $this->emailBlast = $emailBlast;
        $this->merchant = $merchant;
        $this->customer = $customer;
    }

    /**
     * Email info
     *
     * @return array
     */
    public function emailInfo()
    {
        return $this->emailBlast->getEmailInfo();
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
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new EmailBlast(
            $this->emailBlast
                ->replacePlaceholders($this->merchant, $this->customer)
                ->title ?? '',
            $this->emailBlast
                ->replacePlaceholders($this->merchant, $this->customer)
                ->subtitle ?? '',
            $this->emailBlast->banner_image_path ?? '',
            $this->emailBlast->banner_url ?? '',
            $this->merchant,
            $this->customer,
            $this->emailBlast
                ->replacePlaceholders($this->merchant, $this->customer)
                ->replaceVoucherCode($this->customer)
                ->replaceMediaFiles()
                ->replaceDiscordCode()
                ->body ?? '',
            true
        ))
        ->subject(
            $this->emailBlast
                ->replacePlaceholders($this->merchant, $this->customer)
                ->subject
        )
        ->to($notifiable->email);
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
