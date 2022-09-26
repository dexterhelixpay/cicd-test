<?php

namespace App\Observers;

class ProductRecurrenceObserver
{
    /**
     * Handle the product recurrence "created" event.
     *
     * @param  \App\Models\ProductRecurrence  $recurrence
     * @return void
     */
    public function creating($recurrence)
    {
        $this->setDiscount($recurrence);
    }

    /**
     * Handle the product recurrence "updated" event.
     *
     * @param  \App\Models\ProductRecurrence  $recurrence
     * @return void
     */
    public function updating($recurrence)
    {
        $this->setDiscount($recurrence);
    }

    /**
     * Set the discount.
     *
     * @param  \App\Models\ProductRecurrence  $recurrence
     * @return void
     */
    protected function setDiscount($recurrence)
    {
        $recurrence->fill([
            'discount_type_id' => null,
            'discount_value' => null,
        ]);

        if ($recurrence->original_price) {
            $less = $recurrence->original_price - ($recurrence->price ?: 0);
            $discount = floor($less / $recurrence->original_price * 100);

            $recurrence->fill([
                'discount_type_id' => $less ? 2 : null,
                'discount_value' => $less ? max($discount, 1) : null,
            ]);
        }
    }
}
