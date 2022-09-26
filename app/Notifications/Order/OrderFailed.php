<?php

namespace App\Notifications\Order;

use App\Mail\DynamicSubscription as DynamicSubscriptionMail;
use App\Messages\SmsMessage;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SendsMerchantViber;
use App\Notifications\Contracts\SendsViber;
use App\Notifications\Contracts\SmsNotification;
use App\Services\OrderNotificationService;
use App\Traits\SetChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Storage;

class OrderFailed extends SmsNotification implements SendsMail, SendsMerchantViber, SendsViber, Cacheable, ShouldQueue
{
    use Queueable, SetChannels;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The merchant model.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

    /**
     * The order notifcation.
     *
     * @var \App\Models\OrderNotification
     */
    public $notification;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->order = $order->load('subscription.merchant');
        $this->merchant = $order->subscription->merchant;
        $this->notification = (new OrderNotificationService)
            ->getPaymentNotificationForOrder($this->order);
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
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        return "order.failed.{$this->order->getKey()}";
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

        $isInActive = are_all_single_recurrence($subscription->products)
            && $this->order->payment_status_id == PaymentStatus::PAID;

        $error = $this->order->getErrorResponse();

        $options =  [
            'title' => $this->order->has_payment_lapsed && $this->notification
                ? replace_placeholders($this->notification?->headline, $this->order)
                : $error['title'],
            'subtitle' => $this->order->has_payment_lapsed && $this->notification
                ? replace_placeholders($this->notification?->subheader, $this->order)
                : $error['subtitle'],
            'subject' => replace_placeholders($this->notification?->subject, $this->order),
            'payment_headline' => replace_placeholders($this->notification?->payment_headline, $this->order),
            'payment_instructions' => replace_placeholders($this->notification?->payment_instructions, $this->order),
            'payment_button_label' => $this->notification?->payment_button_label ?? 'Pay Now',
            'total_amount_label' => $this->notification?->total_amount_label ?? 'Total Amount',
            'payment_instructions_headline' => replace_placeholders($this->notification?->payment_instructions_headline, $this->order),
            'payment_instructions_subheader' => replace_placeholders($this->notification?->payment_instructions_subheader, $this->order),
            'type' => 'failed',
            'status' => [
                'label' => "Payment Unsuccessful",
                'color' => 'red'
            ],
            'has_pay_button' => true,
            'is_api_booking' => $subscription->is_api_booking,
            'is_console_created_subscription' => $subscription->is_console_booking
                && $this->order->payment_status_id != PaymentStatus::PAID,
            'has_change_button' => true,
            'has_edit_button' => $isInActive ? false : true,
            'has_subscription_convertion_component' => $isInActive,
            'has_order_summary' => $this->order->isInitial()
                && in_array($this->order->order_status_id, [
                    OrderStatus::UNPAID,
                    OrderStatus::INCOMPLETE,
                    OrderStatus::FAILED,
                ]),
            'is_custom_merchant' => in_array($this->merchant->getKey(), setting('CustomMerchants', [])),
            'is_from_merchant' => $subscription->is_console_booking,
            'error' => $this->order->getErrorResponse(),
        ];

        $mail = (new DynamicSubscriptionMail(
            $subscription,
            $this->merchant,
            $this->order->products()->first(),
            $this->order,
            $options
        ))->subject($options['subject']);

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
    public function toSms($notifiable, $isViber = false)
    {
        $contact = data_get($this->merchant->support_contact, 'value', null);
        $contactText = $contact
            ? match (data_get($this->merchant->support_contact, 'type', null)) {
                'url' => "Please contact or visit {$contact} if you have any questions.",
                'email' => "Please contact {$contact} if you have any questions",
                'default' => '',
                }
            : '';

        $error = $this->order->getErrorResponse();
        $paymentLabel = $this->notification?->payment_button_label ?? 'Pay Now';

        return (new SmsMessage)
            ->line(
                $this->order->has_payment_lapsed && $this->notification
                    ? replace_placeholders($this->notification?->headline, $this->order)
                    : $error['title']
            )
            ->line(
                $this->order->has_payment_lapsed && $this->notification
                    ? replace_placeholders($this->notification?->subheader, $this->order)
                    : $error['subtitle']
            )
            ->line($contactText)
            ->line("\nID: {$this->order->subscription->obfuscateKey()}")
            ->line("\n{$paymentLabel}: {$this->order->getEditUrl(false, true, $isViber ? 'viber' : 'sms')}&action=pay&type=failed")
            ->salutation("Thank you,\n{$this->merchant->name}");
    }

    /**
     * {@inheritdoc}
     */
    public function toViber($notifiable)
    {
        return $this->toSms($notifiable, true);
    }

    /**
     * {@inheritdoc}
     */
    public function toMerchantViber($notifiable)
    {
        return (new SmsMessage)
            ->line("{$this->order->subscription->customer->name} tried to pay for order {$this->order->id} but failed.");
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
            'merchant_id' => $this->merchant->getKey(),
        ];
    }
}
