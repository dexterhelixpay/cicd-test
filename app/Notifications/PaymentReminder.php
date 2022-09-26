<?php

namespace App\Notifications;

use App\Mail\PaymentReminder as PaymentReminderMail;
use App\Models\Email;
use App\Models\EmailEvent;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use App\Notifications\Contracts\SendsMail;
use App\Traits\CreateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
class PaymentReminder extends Notification implements SendsMail, ShouldQueue
{
    use Queueable;

    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
     */
    public $subscription;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The product model.
     *
     * @var array
     */
    public $product;

    /**
     * The payment type model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The options.
     *
     * @param array
     */
    public $options;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function __construct(
        Subscription $subscription,
        Merchant  $merchant,
        $product,
        Order $order,
        $options = []
    ) {
        $this->subscription = $subscription;
        $this->merchant = $merchant;
        $this->product = $product;
        $this->order = $order;
        $this->options = $options;
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
        if (!$this->subscription->customer) return;

        $mail = (new PaymentReminderMail(
            $this->subscription,
            $this->merchant,
            $this->product,
            $this->order,
            $this->options
        ))->subject(replace_placeholders($this->options['subject'], $this->order));

        $this->order->attachments()->each(function ($attachment, $index) use (&$mail) {
            if ($attachment->is_invoice) return;

            $asset = filter_var($attachment->getRawOriginal('file_path'), FILTER_SANITIZE_URL);
            $fileName = $attachment->name;

            if (Storage::exists($asset)) {
                $mail->attachData(Storage::get($asset), $fileName, [
                    'mime' => 'application/pdf',
                ]);
            }
        });

        $mail->to($notifiable->email);

        return $mail;
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
