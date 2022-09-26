<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Facades\PesoPay;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class PesoPayController extends Controller
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
                $request->isNotFilled('type')
                // && !$request->hasValidSignature()
            ) {
                throw new Exception;
            }

            return DB::transaction(function () use ($request) {
                switch ($request->input('type')) {
                    case 'payment':
                        return $this->handlePayments($request);

                    default:
                        throw new Exception;
                }
            });
        } catch (Throwable $e) {
            abort(403);
        }
    }

    /**
     * Capture webhook events from PesoPay.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function captureEvent(Request $request)
    {
        $input = $request->input();

        dispatch(function () use ($input) {
            if (!PesoPay::verifyDatafeed($input, $input['secureHash'] ?? '')) {
                return;
            }

            $order = Order::query()
                ->whereIn('payment_type_id', [PaymentType::GCASH, PaymentType::GRABPAY])
                ->satisfied(false)
                ->find(intval($input['Ref'] ?? null));

            if (!$order) {
                return;
            }

            switch ($input['successcode'] ?? null) {
                case '0':
                    $order->forceFill([
                        'payment_status_id' => PaymentStatus::PAID,
                        'payment_info' => ['data_feed' => $input],
                    ]);

                    break;

                case '1':
                default:
                    $order->forceFill([
                        'payment_status_id' => PaymentStatus::FAILED,
                        'payment_info' => ['data_feed' => $input],
                    ]);

                    break;
            }

            $order->update();
        })->afterResponse();

        return 'OK';
    }

    /**
     * Handle redirection for payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handlePayments($request)
    {
        if ($request->isNotFilled('success', 'merchant', 'subscription', 'order')) {
            throw new Exception;
        }

        $merchant = Merchant::findOrFail($request->input('merchant'));
        $subscription = $merchant->subscriptions()->findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));

        $url = $request->input('success')
            ? config('bukopay.url.payment_success')
            : config('bukopay.url.payment_failed');

        $scheme = app()->isLocal() ? 'http' : 'https';
        $query = [
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
        ];

        if ($request->input('isFromFailedPayment')) {
            $query = array_merge($query, ['isFromFailedPayment' => 1]);
        }

        return Redirect::away(
            "{$scheme}://{$merchant->subdomain}.{$url}?" . http_build_query($query)
        );
    }
}
