<?php

namespace App\Notifications;

use App\Mail\EmailBlast;
use Illuminate\Bus\Queueable;
use App\Notifications\Contracts\SendsMail;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeEmailNotification extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The welcome email.
     *
     * @var \App\Models\WelcomeEmail
     */
    public $welcomeEmail;

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
    public function __construct($welcomeEmail, $merchant, $customer)
    {
        $this->welcomeEmail = $welcomeEmail;
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
            $this->welcomeEmail
                ->replacePlaceholders($this->merchant, $this->customer)
                ->title ?? '',
            $this->welcomeEmail
                ->replacePlaceholders($this->merchant, $this->customer)
                ->subtitle ?? '',
            $this->welcomeEmail->banner_image_path ?? '',
            $this->welcomeEmail->banner_url ?? '',
            $this->merchant,
            $this->customer,
            $this->welcomeEmail
                ->replacePlaceholders($this->merchant, $this->customer)
                ->replaceDiscordCode()
                ->body
        ))->subject(
            $this->welcomeEmail
            ->replacePlaceholders($this->merchant, $this->customer)
            ->subject
        )->to($notifiable->email);
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
