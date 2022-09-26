<?php

namespace App\Notifications\Order;

use Carbon\Carbon;
use App\Models\Order;
use App\Traits\SetChannels;
use Illuminate\Support\Str;
use App\Messages\SmsMessage;
use App\Models\PaymentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsMail;
use App\Services\OrderNotificationService;
use App\Notifications\Contracts\SendsViber;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Contracts\SmsNotification;
use App\Notifications\Contracts\SendsMerchantViber;
use App\Mail\DynamicSubscription as DynamicSubscriptionMail;

use function PHPUnit\Framework\matches;

class OrderPaid extends SmsNotification implements SendsMail, SendsMerchantViber, SendsViber, Cacheable, ShouldQueue
{
    use Queueable, SetChannels;

    /**
     * The order model.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The order model.
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
        return "order.paid.{$this->order->getKey()}";
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

        $options =  [
            'title' => replace_placeholders($this->notification?->headline, $this->order),
            'subtitle' => !$this->order->total_price
                ? replace_placeholders('You have successfully paid {merchantName} for a FREE transaction', $this->order)
                : replace_placeholders($this->notification?->subheader, $this->order, 'email'),
            'subject' => replace_placeholders($this->notification?->subject, $this->order),
            'payment_headline' => replace_placeholders($this->notification?->payment_headline, $this->order),
            'payment_instructions' => replace_placeholders($this->notification?->payment_instructions, $this->order),
            'payment_button_label' => $this->notification?->payment_button_label,
            'total_amount_label' => !$this->order->total_price
                ? 'Total Amount'
                : $this->notification?->total_amount_label,
            'payment_instructions_headline' => replace_placeholders($this->notification?->payment_instructions_headline, $this->order),
            'payment_instructions_subheader' => replace_placeholders($this->notification?->payment_instructions_subheader, $this->order),
            'type' => 'success',
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
            'has_attachment' => true,
            'has_subscription_convertion_component' => $isInActive,
            'has_order_summary' => $subscription->is_console_booking
                && are_all_single_recurrence($subscription->products)
                && $this->order->payment_status_id != PaymentStatus::PAID,
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
        ))->subject($options['subject']);

        $this->order->attachments()->each(function ($attachment, $index) use(&$mail) {
            if ($attachment->is_invoice) return;

            $asset = filter_var($attachment->getRawOriginal('file_path'), FILTER_SANITIZE_URL);
            $fileName = $attachment->name;

            // TODO: Add flag for paid invoice
            if (Str::contains($attachment->name, formatId($this->order->created_at, $this->order->id). '-U')) {
                return;
            }

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
        $merchant = $this->order->subscription->merchant;

        $contact = data_get($merchant->support_contact, 'value', null);
        $contactText = $contact
            ? match (data_get($merchant->support_contact, 'type', null)) {
                    'url' => "Please contact or visit {$contact} if you have any questions.",
                    'email' => "Please contact {$contact} if you have any questions",
                    'default' => '',
                }
            : '';

        return (new SmsMessage)
            ->line(replace_placeholders($this->notification?->headline, $this->order))
            ->line(
                !$this->order->total_price
                    ? replace_placeholders('You have successfully paid {merchantName} for a FREE transaction', $this->order)
                    : replace_placeholders($this->notification?->subheader, $this->order)
            )
            ->line($contactText)
            ->line("\nID: {$this->order->subscription->obfuscateKey()}")
            ->salutation("Thank you,\n{$merchant->name}");
    }

    /**
     * {@inheritdoc}
     */
    public function toViber($notifiable)
    {
        return $this->toSms($notifiable);
    }

    /**
     * {@inheritdoc}
     */
    public function toMerchantViber($notifiable)
    {
        $subscription = $this->order->subscription;

        $paymentCount = ordinal_number($subscription->orders()
            ->where('payment_status_id', PaymentStatus::PAID)
            ->count());

        return (new SmsMessage)
            ->line("{$subscription->customer->name} has just paid you. This is the {$paymentCount} payment for their subscription {$subscription->id}.");
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
