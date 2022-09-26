<?php

namespace App\View\Components;

use Illuminate\View\Component;

class InvoiceFooter extends Component
{
    /**
     * The merchant
     *
     * @var array||object
     */
    public $merchant;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($merchant = null)
    {
        $this->merchant = $merchant;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.invoice-footer');
    }
}
