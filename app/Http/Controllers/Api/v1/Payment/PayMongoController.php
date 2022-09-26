<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Http\Controllers\Controller;
use App\Libraries\PayMongo\Payment;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class PayMongoController extends Controller
{
    /**
     * Redirect the request to the appropriate landing page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request)
    {
        try {
            if (
                // !$request->hasValidSignature()
                $request->isNotFilled('type')
            ) {
                throw new Exception;
            }

            switch ($request->input('type')) {
                case 'charged':
                    return $this->handleCharged($request);

                default:
                    throw new Exception;
            }
        } catch (Throwable $e) {
            \Log::info($e);
            abort(403);
        }
    }

    /**
     * Capture webhook events from PayMongo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureEvent(Request $request)
    {
        if ($request->input('data.type') !== 'event') {
            return response()->json();
        }

        switch ($request->input('data.attributes.type')) {
            case 'source.chargeable':
                $this->createPaymentFromSource($request->input('data.attributes.data'));
                break;

            default:

        }

        return response()->json();
    }

    /**
     * Handle redirection for charged payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleCharged($request)
    {
        if ($request->isNotFilled('success', 'merchant', 'subscription', 'order')) {
            throw new Exception;
        }

        $merchant = Merchant::findOrFail($request->input('merchant'));
        $subscription = $merchant->subscriptions()->findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));

        $order->update([
            'payment_status_id' => $request->input('success')
                ? PaymentStatus::CHARGED
                : PaymentStatus::FAILED,
            'order_status_id' => $request->input('success')
                ? OrderStatus::PAID
                : OrderStatus::UNPAID
        ]);

        $url = $request->input('success')
            ? config('bukopay.url.payment_success')
            : config('bukopay.url.payment_failed');

        $scheme = app()->isLocal() ? 'http' : 'https';
        $url = "{$scheme}://{$merchant->subdomain}.{$url}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
        ]);

        if ($request->input('isFromFailedPayment')) {
            $url .= "&isFromFailedPayment=1";
        }

        return Redirect::away($url);
    }

    /**
     * Create a payment from the given source
     *
     * @param  array  $source
     * @return void
     */
    protected function createPaymentFromSource($source)
    {
        $sourceId = data_get($source, 'id');

        if (!$chargeable = $this->getChargeableModel($sourceId)) {
            return;
        }

        Payment::create($sourceId, $chargeable->total_price)
            ->then(function ($payment) use ($chargeable) {
                $chargeable->forceFill([
                    'payment_status_id' => PaymentStatus::PAID,
                    'payment_info' => array_merge(
                        $chargeable->payment_info ?? [],
                        compact('payment')
                    ),
                ]);
            }, function ($e) use ($chargeable) {
                $chargeable->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_info' => array_merge(
                        $chargeable->payment_info ?? [],
                        compact('error')
                    ),
                ]);
            })
            ->wait(false);

        $chargeable->update();
    }

    /**
     * Get the chargeable model from the given source ID.
     *
     * @param  string  $sourceId
     * @return \App\Models\Order|null
     */
    protected function getChargeableModel($sourceId)
    {
        if ($order = Order::firstWhere('payment_info->source->id', $sourceId)) {
            return $order;
        }

        return null;
    }
}
