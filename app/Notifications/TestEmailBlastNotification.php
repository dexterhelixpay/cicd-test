<?php

namespace App\Notifications;

use App\Mail\EmailBlast;
use Illuminate\Bus\Queueable;
use App\Notifications\Contracts\SendsMail;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestEmailBlastNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The email blast model.
     *
     * @var array||object
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
            $this->emailBlast['title'],
            $this->emailBlast['subtitle'],
            $this->emailBlast['banner_image_path'],
            $this->emailBlast['banner_url'],
            $this->merchant,
            $this->customer,
            $this->emailBlast['body'],
            data_get($this->emailBlast, 'has_limited_availability', false)
        ))
        ->subject($this->emailBlast['subject']);
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
