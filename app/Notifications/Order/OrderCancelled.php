<?php

namespace App\Notifications\Order;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Traits\SetChannels;
use App\Messages\SmsMessage;
use App\Models\PaymentStatus;
use Illuminate\Bus\Queueable;
use App\Models\OrderedProduct;
use Illuminate\Support\Facades\Storage;
use App\Notifications\Contracts\Cacheable;
use App\Notifications\Contracts\SendsMail;
use App\Notifications\Contracts\SendsViber;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Contracts\SmsNotification;
use App\Notifications\Contracts\SendsMerchantViber;
use App\Mail\DynamicSubscription as DynamicSubscriptionMail;

class OrderCancelled extends SmsNotification implements SendsMail, SendsMerchantViber, SendsViber, Cacheable, ShouldQueue
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
        $this->order = $order->load('products', 'subscription.merchant');
    }

    /**
     * {@inheritdoc}
     */
    public function cacheKey($notifiable): string|array
    {
        return "order.cancelled.{$this->order->getKey()}";
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

        $subject = ucwords($merchant->subscription_term_singular)." Cancelled - #{$this->order->obfuscateKey()}";
        $lastPaidBillingDate = $subscription->lastPaidOrder?->billing_date->format('F j');

        $options =  [
            'title' => 'Your '.ucwords($merchant->subscription_term_singular).' has ended.',
            'subtitle' => "You will not be charged anymore for this {$merchant->subscription_term_singular}.",
            'payment_headline' => '',
            'payment_instructions' => '',
            'payment_button_label' => '',
            'total_amount_label' => 'Total Amount',
            'payment_instructions_headline' => $subscription->lastPaidOrder ? 'Last Payment' : '',
            'payment_instructions_subheader' => $subscription->lastPaidOrder
                ? "Your last payment was {$lastPaidBillingDate}"
                : '',
            'type' => 'cancelled',
            'subject' => $subject,
            'status' => [
                'label' => 'Cancelled',
                'color' => 'red'
            ],
            'has_pay_button' => false,
            'is_api_booking' => $subscription->is_api_booking,
            'is_console_created_subscription' => $subscription->is_console_booking
                && $this->order->payment_status_id != PaymentStatus::PAID,
            'has_change_button' => false,
            'has_edit_button' => false,
            'has_subscription_convertion_component' => false,
            'has_order_summary' => $this->order->isInitial()
                && in_array($this->order->order_status_id, [
                    OrderStatus::UNPAID,
                    OrderStatus::FAILED,
                    OrderStatus::INCOMPLETE
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
                'url' => "Please contact ﻿or visit {$contact} if you have any questions.",
                'email' => "Please contact {$contact} if you have any questions",
                'default' => '',
            }
            : '';

        return (new SmsMessage)
            ->line("Your {$merchant->subscription_term_singular} has ended.")
            ->line("You will not be charged anymore for this {$merchant->subscription_term_singular}")
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
        $formattedProducts = formatProducts($this->order->products()->get());

        $subscription = $this->order->subscription;

        return (new SmsMessage)
            ->line("{$subscription->customer->name} cancelled their subscription of {$formattedProducts} for subscription {$subscription->id}.");
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
