<?php

namespace App\View\Components;

use Illuminate\View\Component;

class InvoiceDetails extends Component
{

    /**
     * The customer
     *
     * @var array||object
     */
    public $customer;

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
     * The invoice number
     *
     * @var string
     */
    public $invoiceNumber;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($customer = null, $subscription = null, $order = null)
    {
        $this->customer = $customer->load('country');
        $this->subscription = $subscription;
        $this->order = $order;
        $this->invoiceNumber = $subscription->id .'-'. formatId($this->order->created_at, $this->order->id);
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.invoice-details');
    }
}
