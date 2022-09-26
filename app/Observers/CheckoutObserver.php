<?php

namespace App\Observers;

use Illuminate\Support\Facades\URL;
use Vinkla\Hashids\Facades\Hashids;

class CheckoutObserver
{
    /**
     * Handle the checkout "creating" event.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return void
     */
    public function creating($checkout)
    {
        $this->setExpiration($checkout);
    }

    /**
     * Handle the checkout "created" event.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return void
     */
    public function created($checkout)
    {
        $this->setCheckoutUrl($checkout);
    }

    /**
     * Set the checkout URL.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return void
     */
    protected function setCheckoutUrl($checkout)
    {
        $checkout->checkout_url = URL::signedUrl(config('bukopay.url.checkout'), [
            'id' => Hashids::connection('checkout')->encode($checkout->getKey())
        ], now()->addDay()->toDate());

        $checkout->saveQuietly();
    }

    /**
     * Set the expiration timestamp.
     *
     * @param  \App\Models\Checkout  $checkout
     * @return void
     */
    protected function setExpiration($checkout)
    {
        $checkout->expires_at = now()->addDay();
    }
}
