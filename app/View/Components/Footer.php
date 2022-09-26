<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Vinkla\Hashids\Facades\Hashids;
class Footer extends Component
{
    /**
     * If email blast
     *
     * @var boolean
     */
    public $isEmailBlast;

    /**
     * merchant
     *
     * @var object
     */
    public $merchant;

    /**
     * customer
     *
     * @var object
     */
    public $customer;

     /**
     * unsubscribe url
     *
     * @var string
     */
    public $unsubscribeUrl;

     /**
     * The button color
     *
     * @var string
     */
    public $buttonColor;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($merchant = null, $customer = null, $isEmailBlast = false)
    {
        $this->isEmailBlast = $isEmailBlast;
        $this->customer = $customer;
        $this->merchant = $merchant;

        if ($isEmailBlast && $this->merchant) {
            $this->buttonColor = "color:{$merchant->highlight_color};";

            $unsubscribeUrl = config('bukopay.url.unsubscribe');

            $this->unsubscribeUrl = "https://{$this->merchant->subdomain}.{$unsubscribeUrl}?" . http_build_query([
                'cust' => Hashids::connection('customer')->encode($customer->id),
                'action' => 'unsubscribe'
            ]);
        }
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.footer');
    }
}
