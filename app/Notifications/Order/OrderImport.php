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

class OrderImport extends SmsNotification implements SendsMail, SendsViber, Cacheable, ShouldQueue
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
     * Test imail flag.
     *
     * @var boolean
     */
    public $isTestEmail;

    /**
     * The email options.
     *
     * @var array||null
     */
    public $email;

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
     * @param  $options
     * @param  $isTestEmail
     * @return void
     */
    public function __construct(Order $order, $email = [], $isTestEmail = false)
    {
        $this->order = $order->loadMissing('products', 'subscription.merchant');
        $this->merchant = $this->order->subscription->merchant;
        $this->email = $email;
        $this->isTestEmail = $isTestEmail;
        $this->notification = (new OrderNotificationService)
            ->getPaymentNotificationForOrder($this->order);
    }

    /**
     * Email info
     *
     * @return array
     */
    public function emailInfo()
    {
        return $this->order->subscription?->scheduleEmail?->getEmailInfo();
    }

    /**
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        return "order.import.{$this->order->getKey()}";
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

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products)
            && $this->order->payment_status_id != PaymentStatus::PAID;

        $subject = data_get($this->email,'subject', null)
            ? $this->email['subject']
            : replace_placeholders($this->notification->subject, $this->order);

        $options =  [
            'title' => data_get($this->email,'headline', null)
                ? replace_placeholders($this->email['headline'], $this->order)
                : replace_placeholders($this->notification->headline, $this->order),
            'subtitle' => data_get($this->email,'subheader', null)
                ? replace_placeholders($this->email['subheader'], $this->order)
                : replace_placeholders($this->notification->subheader, $this->order),
            'payment_headline' => replace_placeholders($this->notification->payment_headline, $this->order),
            'payment_instructions' => replace_placeholders($this->notification->payment_instructions, $this->order),
            'payment_button_label' => $this->notification->payment_button_label,
            'total_amount_label' => $this->notification->total_amount_label,
            'payment_instructions_headline' => replace_placeholders($this->notification->payment_instructions_headline, $this->order),
            'payment_instructions_subheader' => replace_placeholders($this->notification->payment_instructions_subheader, $this->order),
            'type' => 'payment',
            'subject' => replace_placeholders($subject, $this->order),
            'status' => [
                'label' => 'Payment Pending',
                'color' => '#DAC400'
            ],
            'has_pay_button' => $hasOrderSummary ? false : true,
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
            'banner_image_path' => data_get($this->email, 'banner_image_path', null),
            'is_test_email' => $this->isTestEmail
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

        $customText = data_get($this->email, 'sms_text', null)
            ? replace_placeholders($this->email['sms_text'], $this->order)
            : null;

        return (new SmsMessage)
            ->line(
                data_get($this->email,'headline', null)
                    ? replace_placeholders($this->email['headline'], $this->order)
                    : replace_placeholders($this->notification->headline, $this->order)
            )
            ->line(
                data_get($this->email,'subheader', null)
                    ? replace_placeholders($this->email['subheader'], $this->order)
                    : replace_placeholders($this->notification->subheader, $this->order)
            )
            ->line("{$payNowUrl}&action=payNow")
            ->line($contactText)
            ->line( $customText)
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
