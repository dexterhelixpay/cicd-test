<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\HtmlString;

class NewSubscriberEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The subscription model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
     */
    public $subscription;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code
     * @return void
     */
    public function __construct($merchant, $subscription)
    {
        $this->merchant = $merchant;
        $this->subscription = $subscription;
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

        $console = config('bukopay.url.merchant_console') . '/#/login';

        $totalSubscriber = $this->merchant->subscriptions()
            ->whereNull('completed_at')
            ->whereNull('cancelled_at')
            ->count();
        $customer = $this->subscription->customer;
        $address = "{$customer->address}, {$customer->barangay}, {$customer->city}, {$customer->province}, {$customer->zip_code}";
        $order = $this->subscription->initialOrder;

        if (!$order) return;

        $product = $order->products()->first();
        $productId = formatId($this->subscription->created_at, $this->subscription->id);
        $productCount = $order->products()->count();
        $productDescription = '';

        if ($product) {
            $productDescription = $productCount < 2
                ? $product->title.'<br>'
                : ''.$product->title.'<br>
                   +'.($productCount - 1).' more<br>';
        }

        $paymentType = $this->subscription->paymentType
            ? '<strong style="color:#3d4852 !important;">Payment Method</strong><br>'.
                $this->subscription->paymentType->name
            : '' ;

        return (new MailMessage)
            ->subject('HelixPay - New Subscriber Notification #'.$productId.'')
            ->greeting('Hello!' )
            ->markdown('emails.new-subscriber', [
                'customer' => $customer,
                'merchant' => $this->merchant,
                'totalSubscriber' => $totalSubscriber,
                'address' => $address,
                'productDescription' => $productDescription,
                'paymentType' => $paymentType,
                'console' => $console
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
