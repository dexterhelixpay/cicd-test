<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Facades\Brankas;
use App\Http\Controllers\Controller;
use App\Libraries\Brankas\Direct;
use App\Libraries\Brankas\Transfer;
use App\Models\Order;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class BrankasController extends Controller
{
    /**
     * Redirect the request to the appropriate landing page.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request)
    {
        $data = collect($request)->mapWithKeys(function ($item, $key) {
            return [trim(str_replace('amp;', '', $key)) => $item];
        });

        try {
            if (
                // !$request->hasValidSignature()
                !$data['type']
            ) {
                throw new Exception;
            }

            switch ($data['type']) {
                case 'transferred':
                    return $this->handleTransferred($data);

                default:
                    throw new Exception;
            }
        } catch (Throwable $e) {
            \Log::info($e);
            abort(403);
        }
    }

    /**
     * Capture webhook events from Brankas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureEvent(Request $request)
    {
        if ($request->isNotFilled('transaction_id')) {
            return response()->json();
        }

        $transactionId = $request->input('transaction_id');

        $order = Order::query()
            ->where('payment_type_id', PaymentType::BANK_TRANSFER)
            ->where('payment_info->transaction_id', $transactionId)
            ->first();

        if (!$order) {
            return response()->json();
        }

        $transfer = Transfer::find($request->input('transaction_id'))
            ->then(function ($transfers) {
                return data_get($transfers, 'transfers.0');
            }, function () {
                return null;
            })
            ->wait();

        if (is_null($transfer)) {
            return response()->json();
        }

        switch ($transfer['status'] ?? null) {
            case Brankas::TRANSFER_SUCCESS:
            case Brankas::TRANSFER_COMPLETED:
                $paymentStatus = PaymentStatus::PAID;
                break;

            case Brankas::TRANSFER_ERROR:
            case Brankas::TRANSFER_LOGIN_ERROR:
            case Brankas::TRANSFER_EXPIRED:
                $paymentStatus = PaymentStatus::FAILED;
                break;

            default:
                //
        }

        if (isset($paymentStatus)) {
            $order->payment_status_id = $paymentStatus;
        }

        $order
            ->forceFill([
                'payment_info' => array_merge($order->payment_info ?? [], [
                    'transaction' => $transfer,
                ]),
                'brankas_masked_pan' => data_get(
                    $transfer,
                    'from.identifier',
                    $order->brankas_masked_pan
                ),
            ])
            ->update();

        return response()->json();
    }

    /**
     * Handle redirection for charged payments.
     *
     * @param  $data
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleTransferred($data)
    {
        if (!Arr::has($data, ['success', 'order'])) {
            throw new Exception;
        }

        $order = Order::findOrFail($data['order']);
        $subscription = $order->subscription()->first();
        $merchant = $subscription->merchant()->first();

        $this->updateOrderPayment($order);

        $url = $order->fresh()->payment_status_id === PaymentStatus::PAID
            ? config('bukopay.url.payment_success')
            : config('bukopay.url.payment_failed');

        $scheme = app()->isLocal() ? 'http' : 'https';
        $url = "{$scheme}://{$merchant->subdomain}.{$url}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
        ]);

        if ($data['isFromFailedPayment']) {
            $url .= "&isFromFailedPayment=1";
        }

        return Redirect::away($url);
    }

    /**
     * Update the given order based on Brankas' transaction record.
     *
     * @param  \App\Models\Order  $order
     * @param  int  $tries
     * @return void
     */
    protected function updateOrderPayment($order, $tries = 1)
    {
        $transactionId = data_get($order, 'payment_info.transaction_id');

        $isPending = Direct::get($transactionId)
            ->then(function ($data) use ($order) {
                $transaction = Arr::first(data_get($data, 'transfers', []));

                if (!$transaction) {
                    return true;
                }

                switch ($transaction['status']) {
                    case 'SUCCESS':
                    case 'COMPLETED':
                        $status = PaymentStatus::PAID;
                        break;

                    case 'ERROR':
                    case 'LOGIN_ERROR':
                    case 'EXPIRED':
                    case 'CANCELLED':
                    case 'DENIED':
                    case 'FAILED':
                        $status = PaymentStatus::FAILED;
                        break;

                    default:
                        $status = PaymentStatus::PENDING;
                }

                $order->forceFill([
                    'payment_status_id' => $status,
                    'payment_info' => array_merge(
                        $order->payment_info ?? [],
                        compact('transaction')
                    ),
                    'brankas_masked_pan' => data_get($transaction, 'from.identifier'),
                ]);

                return $status === PaymentStatus::PENDING;
            }, function ($e) use ($order) {
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_info' => array_merge(
                        $order->payment_info ?? [],
                        ['error' => [
                            'code' => $e->getCode(),
                            'message' => $e->getMessage(),
                            'body' => json_decode($e->getResponse()->getBody(), true),
                        ]]
                    ),
                ]);

                return true;
            })
            ->wait();

        if ($isPending) {
            if ($tries >= 5 && $order->payment_status_id !== PaymentStatus::PAID) {
                return $order->update(['payment_status_id' => PaymentStatus::FAILED]);
            }

            sleep($tries - 1);

            $this->updateOrderPayment($order, $tries + 1);
        }

        $order->update();
    }
}
