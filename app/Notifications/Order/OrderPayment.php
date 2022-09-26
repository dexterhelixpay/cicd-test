<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Traits\SetEditUrl;
use App\Models\OrderStatus;
use App\Traits\SetChannels;
use App\Traits\TracksEmail;
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
use App\Mail\DynamicSubscription as DynamicSubscriptionMail;

class OrderPayment extends SmsNotification implements SendsMail, SendsViber, Cacheable, ShouldQueue
{
    use Queueable, SetChannels, SetEditUrl, TracksEmail;

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
        $this->order = $order->load('products', 'subscription.merchant');
        $this->merchant = $this->order->subscription->merchant;
        $this->notification = (new OrderNotificationService)
            ->getPaymentNotificationForOrder($this->order);
    }

    /**
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        return "order.payment.{$this->order->getKey()}";
    }

    /**
     * Email info
     *
     * @return array
     */
    public function emailInfo()
    {
        return $this->order->subscription->getEmailInfo();
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
        if (!$this->order) return;

        $subscription = $this->order->subscription;
        $merchant = $this->order->subscription->merchant;

        $isInActive =  are_all_single_recurrence($subscription->products)
            && $this->order->payment_status_id == PaymentStatus::PAID;


        $subject = $this->notification?->subject;
        $title =  $this->notification?->headline;
        $subtitle =  $this->notification?->headline;

        if (
            ($subscription->is_console_booking || $subscription->is_api_booking)
            &&  $this->order->isInitial()
        ) {
            $subject = $merchant->console_created_email_subject
                ?: 'Start {subscriptionTermSingular} with {merchantName}!';
            $title =  $merchant->console_created_email_headline_text
                ?: 'Start your subscription with {merchantName}';
            $subtitle =  $merchant->console_created_email_subheader_text
                ?: 'Please enter your payment details to activate your {subscriptionTermSingular}.';
        }

        $options =  [
            'title' => replace_placeholders($title, $this->order),
            'subtitle' => replace_placeholders($subtitle, $this->order),
            'subject' => replace_placeholders($subject, $this->order),
            'payment_headline' => replace_placeholders($this->notification?->payment_headline, $this->order),
            'payment_instructions' => replace_placeholders($this->notification?->payment_instructions, $this->order),
            'payment_button_label' => $this->notification?->payment_button_label,
            'total_amount_label' => $this->notification?->total_amount_label,
            'payment_instructions_headline' => replace_placeholders($this->notification?->payment_instructions_headline, $this->order),
            'payment_instructions_subheader' => replace_placeholders($this->notification?->payment_instructions_subheader, $this->order),
            'type' => 'payment',
            'status' => [
                'label' => 'Payment Pending',
                'color' => '#DAC400'
            ],
            'has_pay_button' => $this->order->payment_status_id != PaymentStatus::PAID,
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
    public function toSms($notifiable)
    {
        if (!$this->order) return;

        $merchant = $this->order->subscription->merchant;
        $subscription = $this->order->subscription;

        $contact = data_get($merchant->support_contact, 'value', null);
        $contactText = $contact
            ? match (data_get($merchant->support_contact, 'type', null)) {
                    'url' => "Please contact or visit {$contact} if you have any questions.",
                    'email' => "Please contact {$contact} if you have any questions",
                    'default' => '',
                }
            : '';

        $payNowText = pay_button_text($subscription, $this->order->id);

        $payNowUrl = "\n{$payNowText}: ".$this->setType('before')
            ->setEditUrl(
                $this->order->id,
                $subscription->id,
                $subscription->customer->id,
                $subscription->is_console_booking,
                true,
                true,
                'sms'
            );

        return (new SmsMessage)
            ->line(
                $subscription->is_console_booking && $this->order->isInitial()
                    ? replace_placeholders($merchant->console_created_email_headline_text, $this->order)
                        ?? replace_placeholders('This is a gentle reminder that you need to pay today to {startOrContinue} your {subscriptionTermSingular} with {merchantName}', $this->order)
                    : replace_placeholders($this->notification?->headline, $this->order)
            )
            ->line(
                $subscription->is_console_booking && $this->order->isInitial()
                    ? replace_placeholders($merchant->console_created_email_subheader_text, $this->order)
                        ?? replace_placeholders('Please enter your payment details to activate your {subscriptionTermSingular}.', $this->order)
                    : replace_placeholders($this->notification?->subheader, $this->order)
            )
            ->line("{$payNowUrl}&action=payNow")
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
