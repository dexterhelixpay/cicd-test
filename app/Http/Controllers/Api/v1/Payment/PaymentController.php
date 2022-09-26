<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Libraries\Xendit\EWalletCharge;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Support\Facades\Redirect;
use Vinkla\Hashids\Facades\Hashids;

abstract class PaymentController extends Controller
{
    /**
     * Redirect to storefront.
     *
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Merchant  $merchant
     * @param  bool  $success
     * @param  array  $query
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToStorefront(
        Order $order,
        Subscription $subscription,
        Merchant $merchant,
        string $status,
        array $query = []
    ) {
        $scheme = app()->isLocal() ? 'http' : 'https';

        $url = match ($status) {
            EWalletCharge::STATUS_SUCCESS => config('bukopay.url.payment_success'),
            EWalletCharge::STATUS_PENDING =>  config('bukopay.url.payment_pending'),
            EWalletCharge::STATUS_FAILED, EWalletCharge::STATUS_VOIDED, EWalletCharge::STATUS_REFUNDED
                =>  config('bukopay.url.payment_failed'),
        };

        $query = array_merge($query, [
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
            'success' => (int) in_array($status, [EWalletCharge::STATUS_SUCCESS, EWalletCharge::STATUS_PENDING]),
        ]);

        return Redirect::away(
            "{$scheme}://{$merchant->subdomain}.{$url}?" . http_build_query($query)
        );
    }
}
