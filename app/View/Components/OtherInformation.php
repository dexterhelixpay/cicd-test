<?php

namespace App\View\Components;

use App\Traits\SetEditUrl;
use Illuminate\View\Component;

class OtherInformation extends Component
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
     * If has edit button
     *
     * @var int
     */
    public $hasEditButton;

    /**
     * The merchant model.
     *
     * @var array||object
     */
    public $merchant;

     /**
     * Subscripiton confirmation type
     *
     * @var string
     */
    public $type;

     /**
     * Subscripiton confirmation type
     *
     * @var array||object
     */
    public $customFields;

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
    public function __construct($subscription, $order, $type = null, $isConsoleBooking = false, $hasEditButton = false)
    {
        $this->hasEditButton = $hasEditButton;
        $this->subscription = $subscription;
        $this->merchant = $this->subscription->merchant()->first();
        $this->customFields = collect($this->subscription->other_info)
            ->filter(function ($data) {
                return $data['data_type'] !== 'json'
                    && isset($data['value'])
                    && $data['value'] !== null
                    && $data['value'] !== '';
            });
        $this->order = $order;
        $this->type = $type;
        $this->isConsoleBooking = $isConsoleBooking;

        $this->setType($this->type)
            ->setEditUrl(
                $this->order->id,
                $this->subscription->id,
                $this->subscription->customer->id,
                $this->isConsoleBooking,
                false
            );
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.other-information');
    }
}
