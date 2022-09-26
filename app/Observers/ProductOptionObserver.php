<?php

namespace App\Observers;

class ProductOptionObserver
{
    /**
     * Handle the product option "creating" event.
     *
     * @param  \App\Models\ProductOption  $option
     * @return void
     */
    public function creating($option)
    {
        $this->setCode($option);
    }


    /**
     * Set a generated code if not filled out.
     *
     * @param  \App\Models\ProductOption  $option
     * @return void
     */
    protected function setCode($option)
    {
        if (!isset($option->code)) {
            $option->generateCode();
        }
    }
}
