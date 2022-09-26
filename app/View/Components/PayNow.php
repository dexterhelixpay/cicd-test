<?php

namespace App\View\Components;

use App\Traits\SetEditUrl;
use Illuminate\View\Component;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Support\Facades\Storage;

class PayNow extends Component
{
    use SetEditUrl;
    /**
     * The price due of customer.
     *
     * @var double
     */
    public $duePrice;


    /**
     * The subscription
     *
     * @var array||object
     */
    public $merchant;

    /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;


     /**
     * Subscripiton confirmation type
     *
     * @var string
     */
    public $type;


    /**
     * order id
     *
     * @var int
     */
    public $orderId;

    /**
     * customer id
     *
     * @var int
     */
    public $customerId;

        /**
     * subscription id
     *
     * @var int
     */
    public $subscriptionId;

    /**
     * payment types images
     *
     * @var array
     */
    public $paymentTypeImages;

     /**
     * If console booking
     *
     * @var boolean
     */
    public $isConsoleBooking;

     /**
     * Pay button link
     *
     * @var string
     */
    public $payButtonLink;

    /**
     * The subscription status.
     *
     * @var string
     */
    public $subscriptionStatus;

    /**
     * The subscription.
     *
     * @var object
     */
    public $subscription;

    /**
     * The subscription.
     *
     * @var boolean
     */
    public $startOrContinue;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $duePrice,
        $merchant,
        $type = null,
        $orderId = '',
        $subscriptionId = '',
        $subscription = null,
        $customerId = '',
        $isConsoleBooking = false,
        $subscriptionStatus = null
    ) {
        $this->duePrice = $duePrice;
        $this->merchant = $merchant->load('paymentTypes');
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;
        $this->orderId = $orderId;
        $this->subscriptionId = $subscriptionId;
        $this->subscription = $subscription;
        $this->customerId = $customerId;
        $this->subscriptionStatus = $subscriptionStatus;

        $buttonColor = $merchant->button_background_color ?: $merchant->highlight_color ?: 'black';

        $initialOrder = $subscription->initialOrder()->first();
        $isInitialOrder = $initialOrder->id == $this->orderId;
        $this->startOrContinue = $isInitialOrder ? 'start' : 'continue';

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "background-color:{$buttonColor};";

        $this->setEditUrl(
            $this->orderId,
            $this->subscriptionId,
            $this->customerId,
            $this->isConsoleBooking,
            true
        );

        $this->setUrls();
        $this->setPaymentTypeImages();
    }


    /**
     * Set urls
     *
     */
    public function setUrls()
    {
        $checkout = config('bukopay.url.subscription_checkout');

        $this->payButtonLink = $this->subscriptionStatus == 'Payment Unsuccessful'
            ? "https://{$this->merchant->subdomain}.{$checkout}?" . http_build_query([
                'sub' => Hashids::connection('subscription')->encode($this->subscriptionId),
                'ord' => Hashids::connection('order')->encode($this->orderId),
                'success' => false
            ])
            : $this->editUrl;
    }

    protected function setPaymentTypeImages()
    {
        $paymentTypeImages = [];

        collect($this->merchant->paymentTypes)
            ->each(function ($paymentType) use(&$paymentTypeImages) {
                $isEnabled = $paymentType->pivot->is_globally_enabled && $paymentType->pivot->is_enabled;

                if (!$isEnabled) return;

                switch ($paymentType->name) {
                    case 'Credit/Debit Card':
                        array_push($paymentTypeImages, Storage::url("images/card_types/visa.png"));
                        array_push($paymentTypeImages, Storage::url("images/card_types/master-card.png"));
                        break;

                    case 'Paymaya Wallet':
                        array_push($paymentTypeImages, Storage::url('images/payment_types/paymaya.png'));
                        break;

                    case 'Bank Transfer':
                        collect(json_decode($paymentType->pivot->payment_methods))
                            ->each(function ($bank) use(&$paymentTypeImages) {
                                if ($bank->is_globally_enabled && $bank->is_enabled) {
                                    array_push($paymentTypeImages, str_replace('svg', 'png', $bank->image_path));
                                }
                            });
                        break;

                    case 'Gcash':
                        array_push($paymentTypeImages, Storage::url('images/payment_types/gcash.png'));
                        break;

                    case 'GrabPay':
                        array_push($paymentTypeImages, Storage::url('images/payment_types/grabpay.png'));
                        break;
                    default:
                        //
                }
            });

        $this->paymentTypeImages = $paymentTypeImages;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.pay-now');
    }
}
