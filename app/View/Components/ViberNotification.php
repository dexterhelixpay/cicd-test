<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

class ViberNotification extends Component
{
    /**
     * The viber image
     *
     * @var string
     */
    public $viberImage;

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
        $this->viberImage = Storage::url('images/viber_notification.png');
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.viber-notification');
    }
}
