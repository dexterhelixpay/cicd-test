<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Vinkla\Hashids\Facades\Hashids;

class SubscriptionConvertion extends Component
{
    /**
     * The merchant
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
     * The edit url
     *
     * @var string
     */
    public $editUrl;

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
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($merchant = null, $subscriptionId = null, $orderId = null)
    {
        $this->merchant = $merchant;
        $this->subscriptionId = $subscriptionId;
        $this->orderId = $orderId;

        $buttonColor = $merchant->button_background_color ?: $merchant->highlight_color ?: 'black';

        $this->buttonColor = strpos($buttonColor, 'linear-gradient') !== false
            ? "background-image:{$buttonColor};"
            : "background-color:{$buttonColor};";

        $editUrl = config('bukopay.url.edit');

        $this->editUrl = "https://{$this->merchant->subdomain}.{$editUrl}?" . http_build_query([
            'isSingleRecurrenceConvertion' => true,
            'sub' => Hashids::connection('subscription')->encode($subscriptionId),
            'ord' => Hashids::connection('order')->encode($orderId),
        ]);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.subscription-convertion');
    }
}
