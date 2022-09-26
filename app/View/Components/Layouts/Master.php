<?php

namespace App\View\Components\Layouts;

use Illuminate\View\Component;

class Master extends Component
{
    /**
     * If email blast
     *
     * @var boolean
     */
    public $isEmailBlast;

    /**
     * If test email
     *
     * @var boolean
     */
    public $isTestEmail;

    /**
     * merchant
     *
     * @var object
     */
    public $merchant;

    /**
     * merchant
     *
     * @var object
     */
    public $customer;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($merchant = null, $customer = null, $isEmailBlast = false, $isTestEmail = false)
    {
        $this->isEmailBlast = $isEmailBlast;
        $this->isTestEmail = $isTestEmail;
        $this->merchant = $merchant;
        $this->customer = $customer;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.layouts.master');
    }
}
