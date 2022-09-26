<?php

namespace App\Observers\v2;

use App\Services\ProductService;

class ProductVariantObserver
{
    /**
     * Handle the variant "updated" event.
     *
     * @param  \App\Models\v2\ProductVariant  $variant
     * @return void
     */
    public function updated($variant)
    {
        if ($variant->wasChanged('stock')) {
            $service = new ProductService;

            $service->cascadeStocks($variant);
            $service->updateTotalStocks($variant->product);
        }

        if ($variant->wasChanged('sold')) {
            $service = new ProductService;

            if ($variant->product) {
                $service->updateTotalSold($variant->product);
            }
        }
    }

    /**
     * Handle the variant "deleting" event.
     *
     * @param  \App\Models\v2\ProductVariant  $variant
     * @return void
     */
    public function deleting($variant)
    {
        $this->deleteRelationships($variant);
    }

    /**
     * Delete related models.
     *
     * @param  \App\Models\v2\ProductVariant  $variant
     * @return void
     */
    protected function deleteRelationships($variant)
    {
        $variant->optionValues()->detach();
    }
}
