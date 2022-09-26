<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\Support\Facades\Storage;

class PayInstruction extends Component
{
    /**
     * The payment icon
     *
     * @var string
     */
    public $icon;

    /**
     * The background color
     *
     * @var string
     */
    public $backgroundColor;

    /**
     * The instruction header
     *
     * @var string
     */
    public $header;

    /**
     * The instruction subheader
     *
     * @var string
     */
    public $subheader;

    /**
     * The order
     *
     * @var array||object
     */
    public $order;

    /**
     * The order
     *
     * @var array||object
     */
    public $subsription;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct(
        $order = null,
        $subscription = null,
        $backgroundColor = null,
        $header = null,
        $subheader = null,
    ) {
        $this->order = $order;
        $this->subsription = $subscription;
        $this->backgroundColor = $backgroundColor;
        $this->header = $header;
        $this->subheader = $subheader;
        $this->icon = true // TODO: apply order notification condition
                ? Storage::url('images/assets/calendar_v3.png')
                : Storage::url('images/assets/arrow.png');
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.pay-instruction');
    }
}
