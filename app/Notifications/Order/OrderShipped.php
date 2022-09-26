<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Traits\SetChannels;
use App\Messages\SmsMessage;
use App\Models\PaymentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SendsViber;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Contracts\SmsNotification;
use App\Mail\DynamicSubscription as DynamicSubscriptionMail;

class OrderShipped extends SmsNotification implements SendsMail, SendsViber, Cacheable, ShouldQueue
{
    use Queueable, SetChannels;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load('subscription.merchant');
    }

    /**
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        return "order.shipped.{$this->order->getKey()}";
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return $this->channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $subscription = $this->order->subscription;
        $merchant = $this->order->subscription->merchant;

        $isInActive =  are_all_single_recurrence($subscription->products)
            && $this->order->payment_status_id == PaymentStatus::PAID;
        $subject = "#{$this->order->obfuscateKey()} - Shipped";

        $options =  [
            'title' => $merchant->shipped_email_headline_text
                ?? 'Your order has been shipped!',
            'subtitle' => $merchant->shipped_email_subheader_text
                ?? 'Look for a notification from the delivery rider when your order is arriving.',
            'payment_headline' => '',
            'payment_instructions' => '',
            'payment_button_label' => '',
            'total_amount_label' => 'Total Amount',
            'payment_instructions_headline' => '',
            'payment_instructions_subheader' => '',
            'type' => 'shipped',
            'subject' => $subject,
            'status' => [
                'label' => 'Active',
                'color' => 'green'
            ],
            'has_pay_button' => false,
            'is_api_booking' => $subscription->is_api_booking,
            'is_console_created_subscription' => $subscription->is_console_booking
                && $this->order->payment_status_id != PaymentStatus::PAID,
            'has_change_button' => false,
            'has_edit_button' => $isInActive ? false : true,
            'has_subscription_convertion_component' => $isInActive,
            'has_order_summary' => $this->order->isInitial()
                && in_array($this->order->order_status_id, [
                    OrderStatus::UNPAID,
                    OrderStatus::INCOMPLETE,
                    OrderStatus::FAILED,
                ]),
            'is_custom_merchant' => in_array($merchant->getKey(), setting('CustomMerchants', [])),
            'is_from_merchant' => $subscription->is_console_booking,
            'error' => $this->order->getErrorResponse(),
        ];

        $mail = (new DynamicSubscriptionMail(
            $subscription,
            $merchant,
            $this->order->products()->first(),
            $this->order,
            $options
        ))->subject($subject);

        $this->order->attachments()->each(function ($attachment, $index) use(&$mail) {
            if ($attachment->is_invoice) return;

            $asset = filter_var($attachment->getRawOriginal('file_path'), FILTER_SANITIZE_URL);
            $fileName = $attachment->name;

            if (Storage::exists($asset)) {
                $mail->attachData(Storage::get($asset), $fileName, [
                    'mime' => 'application/pdf',
                ]);
            }
        });

        return $mail;
    }

    /**
     * {@inheritdoc}
     */
    public function toSms($notifiable)
    {
        return (new SmsMessage)
            ->line('Your order has been shipped! Look for a notification from the delivery rider when your order is arriving!')
            ->salutation("Thank you,\n{$this->order->subscription->merchant->name}");
    }

    /**
     * {@inheritdoc}
     */
    public function toViber($notifiable)
    {
        return $this->toSms($notifiable);
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
            'message' => (string) $this->toSms($notifiable),
            'order_id' => $this->order->getKey(),
            'subscription_id' => $this->order->subscription->getKey(),
            'merchant_id' => $this->order->subscription->merchant->getKey(),
        ];
    }
}
