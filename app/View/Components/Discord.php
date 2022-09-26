<?php

namespace App\View\Components;

use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

class Discord extends Component
{

    /**
     * The discord image
     *
     * @var string
     */
    public $discordImage;

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
        $this->discordImage = Storage::url('images/assets/discord_white.png');
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.discord');
    }
}
