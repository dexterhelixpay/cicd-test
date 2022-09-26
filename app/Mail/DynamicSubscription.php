<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Merchant;
use App\Traits\SetEditUrl;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\OrderedProduct;
use Illuminate\Support\Carbon;
use App\Models\SubscribedProduct;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DynamicSubscription extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, SetEditUrl;

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
     * The options.
     *
     * @param array
     */
    public $options;

    /**
     * The billing date
     *
     * @var string
     */
    public $billingDate;

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
     * The customer profile page url
     *
     * @var string
     */
    public $customerProfileUrl;

    /**
     * The first word of the subtitle
     *
     * @var string
     */
    public $startOrContinue;


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
     * Viber suibscription button link
     *
     * @var string
     */
    public $viberSubscriptionLink;

     /**
     * Viber suibscription button link
     *
     * @var string
     */
    public $discordLink;

    /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;

    /**
     * The button color
     *
     * @var boolean
     */
    public $hasDiscordButton;

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
        OrderedProduct $product,
        Order $order,
        $options = []
    ) {
        $this->subscription = $subscription;
        $this->merchant = $merchant;
        $this->product = $product;
        $this->order = $order;
        $this->options = $options;
        $this->fontCss = $this->merchant->email_font_settings;

        $initialOrder = $subscription->initialOrder()->first();
        $this->startOrContinue = !$initialOrder || $initialOrder->is($order)
            ? 'Start'
            : 'Continue';

        $this->payButtonText = $options['payment_button_label'];

        $this->billingDate = Carbon::parse($order->billing_date)->format('F d');

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
        $customerProfileUrl = config('bukopay.url.profile');

        $this->customerProfileUrl = "https://{$this->merchant->subdomain}.{$customerProfileUrl}";

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
                'success' => false,
                'type' => $this->options['type']
            ])
            : $this->editUrl;


        $this->viberSubscriptionLink = "{$this->customerProfileUrl}?".http_build_query([
            'isViberSubscription' => true,
            'type' => $this->options['type']
        ]);

        $isDiscordEmailInviteEnabled =  Setting::where('key', 'IsDiscordEmailInviteEnabled')
            ->first()
            ?->value ?? false;

        $this->hasDiscordButton = $isDiscordEmailInviteEnabled
            && $this->merchant->is_discord_email_invite_enabled
            && $this->subscription
                ->products()
                ->get()
                ->contains(function (SubscribedProduct $subscribedProduct) {
                    return $subscribedProduct->product?->is_discord_invite_enabled ?? false;
                });

        $this->discordLink = env('APP_URL').'/v1/discord/redirect?'.http_build_query([
            'subscription_id' => $this->subscription->id
        ]);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view("emails.subscriptions.subscription");
    }
}
