<?php

namespace App\Mail;

use App\Traits\SetEditUrl;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Vinkla\Hashids\Facades\Hashids;

class PaymentReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, SetEditUrl;

    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
     */
    public $subscription;

    /**
     * The subscription model.
     *
     * @var \App\Models\Subscription
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
     * The header bg color
     *
     * @var string
     */
    public $headerBgColor;

    /**
     * The bg color
     *
     * @var string
     */
    public $bgColor;

    /**
     * Has other info
     *
     * @var string
     */
    public $hasOtherInfo;

    /**
     * Pay button text
     *
     * @var string
     */
    public $payButtonText;

    /**
     * Pay button link
     *
     * @var string
     */
    public $payButtonLink;

    /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;

    /**
     * The font settings
     *
     * @var string
     */
    public $fontCss;


    /**
     * Create a new message instance.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function __construct(
        Subscription $subscription,
        Merchant $merchant,
        $product,
        Order $order,
        $options = []
    ) {
        $this->subscription = $subscription;
        $this->merchant = $merchant;
        $this->product = $product;
        $this->order = $order;
        $this->options = $options;
        $this->fontCss = $this->merchant->email_font_settings;
        $this->hasOtherInfo = $this->subscription->hasOtherInfo();

        $this->payButtonText = data_get($options, 'payment_button_label')
            ?? pay_button_text($this->subscription,$this->order->id);

        $this->setColors();
        $this->setUrls();
    }

    /**
     * Set colors
     *
     */
    public function setColors()
    {
        $headerBackgroundColor = $this->merchant->header_background_color ?? 'rgba(247, 250, 252, 1)';
        $backgroundColor = $this->merchant->background_color ?? 'rgba(247, 250, 252, 1)';

        $this->headerBgColor = strpos($headerBackgroundColor, 'linear-gradient') !== false
            ? "background-image:{$headerBackgroundColor};"
            : "background-color:{$headerBackgroundColor};";

        $this->bgColor = strpos($backgroundColor, 'linear-gradient') !== false
            ? "background-image:{$backgroundColor};"
            : "background-color:{$backgroundColor};";

        $buttonColor = $this->merchant->button_background_color ?: $this->merchant->highlight_color ?: 'black';

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "background-color:{$buttonColor};";
    }

    /**
     * Set urls
     *
     */
    public function setUrls()
    {
        $this->setType($this->options['type'])
            ->setEditUrl(
            $this->order->id,
            $this->subscription->id,
            $this->subscription->customer->id,
            $this->options['is_console_created_subscription'] ?? false,
            true
        );


        $checkout = config('bukopay.url.subscription_checkout');

        $this->payButtonLink = ($options['subscription_status'] ?? 'Payment Pending') == 'Payment Unsuccessful'
            ? "https://{$this->merchant->subdomain}.{$checkout}?" . http_build_query([
                'sub' => Hashids::connection('subscription')->encode($this->subscription->id),
                'ord' => Hashids::connection('order')->encode($this->order->id),
                'success' => false
            ])
            : $this->editUrl;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.subscriptions.payment-reminder');
    }
}
