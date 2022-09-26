<?php

namespace App\View\Components;

use Illuminate\View\Component;

class HeaderBanner extends Component
{
    /**
     * The background color.
     *
     * @var string
     */
    public $bgColor;

    /**
     * The image path of the banner.
     *
     * @var string
     */
    public $imagePath;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($bgColor, $imagePath)
    {
        $this->bgColor = $bgColor;
        $this->imagePath = $imagePath;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.header-banner');
    }
}
