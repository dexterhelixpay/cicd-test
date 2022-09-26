<?php

namespace App\Services;

use App\Models\Merchant;

class ShippingMethodService
{
    /**
     * Guess the shipping method for the given province.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  string  $province
     * @return \App\Models\ShippingMethod|null
     */
    public function guess(Merchant $merchant, string $province)
    {
        $shippingMethod = $merchant->shippingMethods()
            ->whereHas('provinces', function ($query) use ($province) {
                $query
                    ->where('name', $province)
                    ->orWhereJsonContains('alt_names', $province);
            })
            ->first();

        return $shippingMethod
            ?? $merchant->shippingMethods()->doesntHave('provinces')->first();
    }
}
