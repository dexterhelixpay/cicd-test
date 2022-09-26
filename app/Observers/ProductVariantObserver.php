<?php

namespace App\Observers;

class ProductVariantObserver
{
    /**
     * Handle the product variant "creating" event.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    public function creating($variant)
    {
        if (!$variant->title) {
            $variant->title = $variant->product->title;
        }
    }

    /**
     * Handle the product variant "created" event.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    public function created($variant)
    {
        $this->setInitialDiscountPercentage($variant);
    }

    /**
     * Handle the product variant "updated" event.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    public function updated($variant)
    {
        $this->setInitialDiscountPercentage($variant);
    }

    /**
     * Handle the product variant "deleting" event.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    public function deleting($variant)
    {
        $this->deleteRelationships($variant);
    }

    /**
     * Handle the product variant "synced" event.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @param  string  $relation
     * @param  array  $properties
     * @return void
     */
    public function synced($variant, $relation, $properties)
    {
        if ($relation === 'optionValues') {
            $this->updateTitle($variant);
        }
    }

    /**
     * Delete related models.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    protected function deleteRelationships($variant)
    {
        $variant->optionValues()->detach();
    }

    /**
     * Set the initial discount percentage.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    protected function setInitialDiscountPercentage($variant)
    {
        $wasChanged = $variant->wasRecentlyCreated
            || $variant->wasChanged(
                'price',
                'initially_discounted_price',
                'initial_discount_label',
                'initially_discounted_order_count',
                'subheader',
                'is_discount_label_enabled'
            );

        if (
            !$wasChanged
            || !$variant->initially_discounted_order_count
            || !$variant->price
        ) {
            return $variant->setAttribute('initial_discount_percentage', null)->saveQuietly();
        }

        $percent = ($variant->price - ($variant->initially_discounted_price ?: 0)) / $variant->price;
        $percent = floor($percent * 100);

        if (!$percent && $variant->price != $variant->initially_discounted_price) {
            $percent = 1;
        }

        $variant->setAttribute('initial_discount_percentage', $percent ?: null)->saveQuietly();
    }

    /**
     * Update the variant's title based on option values.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return void
     */
    public function updateTitle($variant)
    {
        $values = $variant->optionValues()->pluck('name');

        $variant->updateQuietly([
            'title' => $values->isEmpty()
                ? $variant->product->title
                : $values->join(' / '),
        ]);
    }
}
