<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Facades\Xendit;
use App\Libraries\Xendit\EWalletCharge;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class XenditController extends PaymentController
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
     * Capture webhook events from Xendit.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function captureEvent(Request $request)
    {
        if (!$request->filled('event', 'data') || !$request->hasHeader('x-callback-token')) {
            return response()->json();
        }

        // TODO: Add callback token validation using merchant's Xendit account.

        $data = $request->input('data');

        switch ($request->input('event')) {
            case 'ewallet.capture':
                dispatch(function () use ($data) {
                    $order = Order::query()
                        ->whereIn('payment_type_id', [PaymentType::GCASH, PaymentType::GRABPAY])
                        ->where('payment_info->charge->id', $data['id'])
                        ->where('payment_info->charge->reference_id', $data['reference_id'])
                        ->satisfied(false)
                        ->first();

                    if (!$order || $order->refresh()->isPaid()) {
                        return;
                    }

                    $order->payment_info = ['charge' => $data];

                    switch ($data['status']) {
                        case EWalletCharge::STATUS_SUCCESS:
                            $order->payment_status_id = PaymentStatus::PAID;
                            break;

                        case EWalletCharge::STATUS_PENDING:
                            $order->payment_status_id = PaymentStatus::INCOMPLETE;
                            break;

                        case EWalletCharge::STATUS_FAILED:
                        default:
                            $order->payment_status_id = PaymentStatus::FAILED;
                    }

                    $order->save();
                })->delay(30)->afterResponse();

                break;
        }

        return response()->json();
    }

    /**
     * Handle redirection for payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handlePayments($request)
    {
        if ($request->isNotFilled('merchant', 'order', 'subscription')) {
            throw new Exception;
        }

        $merchant = Merchant::with('xenditAccount')->findOrFail($request->input('merchant'));
        $subscription = $merchant->subscriptions()->findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));

        if (!$chargeId = data_get($order, 'payment_info.charge.id')) {
            throw new Exception;
        }

        $response = Xendit::eWalletCharges()
            ->find($chargeId, $merchant->xenditAccount->xendit_account_id);

        if ($response->successful()) {
            $order->payment_info = ['charge' => $response->json()];
        }

        if ($order->refresh()->isPaid()) {
            return $this->redirectToStorefront($order, $subscription, $merchant, EWalletCharge::STATUS_SUCCESS);
        }

        return $this->redirectToStorefront(
            $order,
            $subscription,
            $merchant,
            $response->successful()
                ? data_get($order, 'payment_info.charge.status')
                : EWalletCharge::STATUS_FAILED
        );
    }
}
