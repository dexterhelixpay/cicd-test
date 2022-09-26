<?php

namespace App\Notifications;

use App\Mail\DynamicSubscription as DynamicSubscriptionMail;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderedProduct;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use App\Notifications\Contracts\SendsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class DynamicSubscriptionNotification extends Notification implements SendsMail, ShouldQueue
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
     * The ordered product model.
     *
     * @var \App\Models\OrderedProduct
     */
    public $product;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The email options.
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
        OrderedProduct $product,
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

        $mail = (new DynamicSubscriptionMail(
            $this->subscription,
            $this->merchant,
            $this->product,
            $this->order,
            $this->options
        ))->subject(replace_placeholders($this->options['subject'], $this->order));

        if ($this->options['has_attachment']) {
            $this->order->attachments()->each(function ($attachment, $index) use(&$mail) {
                $invoiceNo = '#'.formatId($this->order->created_at, $this->order->id). ' - '.($index + 1);

                $asset = filter_var($attachment->getRawOriginal('file_path'), FILTER_SANITIZE_URL);

                if (Storage::exists($asset)) {
                    $mail->attachData(Storage::get($asset), "Invoice {$invoiceNo}", [
                        'mime' => 'application/pdf',
                    ]);
                }
            });
        }

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
