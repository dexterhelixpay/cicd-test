<?php

namespace App\View\Components;

use App\Traits\SetEditUrl;
use Illuminate\View\Component;

class CustomerDetails extends Component
{
    use SetEditUrl;

    /**
     * The subscription of customer.
     *
     * @var object
     */
    public $subscription;

    /**
     * The edit url
     *
     * @var string
     */
    public $editUrl;

    /**
     * The subscription of customer.
     *
     * @var object
     */
    public $order;

    /**
     * The merchant
     *
     * @var array||object
     */
    public $merchant;

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
     * If has pay button
     *
     * @var bool
     */
    public $hasPayButton;

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
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $subscription,
        $order,
        $type = null,
        $isConsoleBooking = false,
        $hasEditButton = false,
        $isPaymentReminder = false,
        $hasPayButton = false,
    ) {
        $this->hasEditButton = $hasEditButton;
        $this->hasPayButton = $hasPayButton;
        $this->isPaymentReminder = $isPaymentReminder;
        $this->subscription = $subscription;
        $this->merchant = $subscription->merchant;
        $this->order = $order;
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;

        $this->setType($this->type)
            ->setEditUrl(
                $this->order->id,
                $this->subscription->id,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
                $hasPayButton ?: $isPaymentReminder
            );
    }
    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.customer-details');
    }
}
