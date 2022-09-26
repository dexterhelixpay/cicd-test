<?php

namespace App\View\Components;

use App\Models\OrderStatus;
use App\Traits\SetEditUrl;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;
use Vinkla\Hashids\Facades\Hashids;

class SubscriptionStatus extends Component
{
    use SetEditUrl;
    /**
     * The subscription status.
     *
     * @var string
     */
    public $subscriptionStatus;

    /**
     * The support contact.
     *
     * @var string
     */
    public $contactUs;

    /**
     * The support contact type.
     *
     * @var string
     */
    public $contactType;

    /**
     * The subscription status color.
     *
     * @var string
     */
    public $subscriptionStatusColor;

    /**
     * The header title
     *
     * @var string
     */
    public $title;

    /**
     * order id
     *
     * @var int
     */
    public $orderId;


     /**
     * Subscription id
     *
     * @var int
     */
    public $subscriptionId;

    /**
     * The merchant
     *
     * @var array||object
     */
    public $merchant;

    /**
     * The skip url
     *
     * @var string
     */
    public $skipUrl;


    /**
     * The cancel url
     *
     * @var string
     */
    public $cancelUrl;

    /**
     * If has edit button
     *
     * @var int
     */
    public $hasEditButton;


    /**
     * If is payment reminder
     *
     * @var int
     */
    public $isPaymentReminder;


    /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;


    /**
     * The subscription
     *
     * @var array||object
     */
    public $subscription;


    /**
     * The order
     *
     * @var array||object
     */
    public $order;

     /**
     * Subscripiton confirmation type
     *
     * @var string
     */
    public $type;

    /**
    * If console booking
    *
    * @var boolean
    */
   public $isConsoleBooking;

    /**
    * If with next order
    *
    * @var boolean
    */
   public $isWithNextOrder;


    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $subscriptionStatus,
        $subscriptionStatusColor = 'black',
        $title = '',
        $orderId = '',
        $subscriptionId = '',
        $merchant = null,
        $hasEditButton = false,
        $isPaymentReminder = false,
        $subscription = null,
        $order = null,
        $type = null,
        $isConsoleBooking = false
    ) {
        $this->subscription = $subscription;
        $this->order = $order;
        $this->isPaymentReminder = $isPaymentReminder;
        $this->hasEditButton = $hasEditButton;
        $this->title = $title;
        $this->subscriptionStatus = $subscriptionStatus;
        $this->subscriptionStatusColor = $subscriptionStatusColor;
        $this->orderId = $orderId;
        $this->subscriptionId = $subscriptionId;
        $this->merchant = $merchant;
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;
        $this->isWithNextOrder = in_array($this->type, ['success','skipped','cancelled','shipped','confirmed'])
            ? (optional($this->order->nextOrder)->id ? optional($this->order->nextOrder)->id : false)
            : true;

        $this->contactUs =  data_get($merchant->support_contact, 'value', null);
        $this->contactType =  data_get($merchant->support_contact, 'type', null);

        $buttonColor = $merchant->button_background_color ?: $merchant->highlight_color ?: 'black';

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "background-color:{$buttonColor};";

        $this->setSubscriptionStatus();
        $this->setType($this->type)
            ->setEditUrl(
                $this->orderId,
                $this->subscriptionId,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
                true
            );

        $this->setUrls();
    }

    /**
     * Set urls
     *
     */
    public function setUrls()
    {
        $skipUrl = config('bukopay.url.skip');
        $cancelUrl = config('bukopay.url.cancel');
        $checkout = config('bukopay.url.subscription_checkout');
        $orderId = in_array($this->type, ['success','skipped','cancelled','shipped','confirmed'])
            ? optional($this->order->nextOrder)->id
            : $this->orderId;

        $this->skipUrl = "https://{$this->merchant->subdomain}.{$skipUrl}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($this->subscriptionId),
            'ord' => Hashids::connection('order')->encode($orderId),
        ]);


        $this->cancelUrl = "https://{$this->merchant->subdomain}.{$cancelUrl}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($this->subscriptionId),
            'ord' => Hashids::connection('order')->encode($orderId),
        ]);

        $this->payButtonLink = $this->subscriptionStatus == 'Payment Unsuccessful'
            ? "https://{$this->merchant->subdomain}.{$checkout}?" . http_build_query([
                'sub' => Hashids::connection('subscription')->encode($this->subscriptionId),
                'ord' => Hashids::connection('order')->encode($orderId),
                'success' => false,
                'type' => $this->type
            ])
            : $this->editUrl;
    }

    /**
     * Set subscription status
     *
     */
    public function setSubscriptionStatus()
    {
        $isLessThanThreeDaysBeforePayment = Carbon::parse($this->order->billing_date)
            ->diffInDays(Carbon::now()) < 3;

        $isPaidInitialOrder = $this->subscription->initialOrder->order_status_id == OrderStatus::PAID;

        if (
            (!$isLessThanThreeDaysBeforePayment
            && $isPaidInitialOrder
            && in_array($this->order->order_status_id,[
                OrderStatus::UNPAID,
                OrderStatus::INCOMPLETE,
                OrderStatus::FAILED
            ]))
            || $this->order->order_status_id == OrderStatus::PAID
            && !$this->subscription->cancelled_at
        ) {
            $this->subscriptionStatus = 'Active';
            $this->subscriptionStatusColor = 'green';
        }

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.subscription-status');
    }
}
