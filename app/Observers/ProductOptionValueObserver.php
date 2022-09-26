<?php

namespace App\Observers;

class ProductOptionValueObserver
{
    /**
     * Handle the product option value "creating" event.
     *
     * @param  \App\Models\ProductOptionValue  $value
     * @return void
     */
    public function creating($value)
    {
        $this->setValue($value);
    }

    /**
     * Set a generated code if not filled out.
     *
     * @param  \App\Models\ProductOptionValue  $valuex
     * @return void
     */
    protected function setValue($value)
    {
        if (!isset($value->value)) {
            $value->generateValue();
        }
    }
}
