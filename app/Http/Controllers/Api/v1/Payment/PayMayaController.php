<?php

namespace App\Http\Controllers\Api\v1\Payment;

use App\Facades\PayMaya as OldPayMaya;
use App\Facades\v2\PayMaya;
use App\Http\Controllers\Controller;
use App\Libraries\PayMaya\Payment;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\Subscription;
use App\Services\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class PayMayaController extends Controller
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

            return DB::transaction(function () use ($request) {
                switch ($request->input('type')) {
                    case 'card_verification':
                        return $this->handleCardVerification($request);

                    case 'wallet_verification':
                        return $this->handleWalletVerification($request);

                    case 'card_payment':
                        return $this->handleCardPayments($request);

                    case 'wallet_payment':
                        return $this->handleWalletPayment($request);

                    default:
                        throw new Exception;
                }
            });
        } catch (Throwable $e) {
            abort(403);
        }
    }

    /**
     * Capture webhook events from PayMaya.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function captureEvent(Request $request)
    {
        $order = Order::query()
            ->where('payment_type_id', PaymentType::CARD)
            ->where('payment_info->payment->id', $request->input('id'))
            ->first();

        if (!$order || $order?->payment_status_id == PaymentStatus::PAID) {
            return response()->json();
        }

        switch ($request->input('status')) {
            case Payment::PAYMENT_SUCCESS:
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::PAID,
                    'payment_info' => ['payment' => $request->input()],
                ]);

                break;

            case Payment::PAYMENT_FAILED:
            case Payment::PAYMENT_EXPIRED:
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_info' => ['payment' => $request->input()],
                ]);

                break;

            default:
                //
        }

        $order->update();

        return response()->json();
    }

    /**
     * Valdiate Card Details
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateCard(Request $request)
    {
        $request->validate([
            'data.subscription_id' => [
                'required',
                Rule::exists('subscriptions', 'id')->whereNull('deleted_at'),
            ],
        ]);

        $subscription = Subscription::findOrFail($request->input('data.subscription_id'));

        return response()->json([], $subscription->isCardVaulted() ? 200 : 400);
    }

    /**
     * Handle redirection for wallet verifications.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleWalletVerification($request)
    {
        if ($request->isNotFilled('success', 'subscription', 'order')) {
            throw new Exception;
        }

        $subscription = Subscription::findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));
        $merchant = $subscription->merchant()->first();

        return $this->redirectToStoreFront(
            $request,
            $subscription,
            $order,
            $merchant,
            $request->input('success')
        );
    }

    /**
     * Handle redirection for wallet payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleWalletPayment($request)
    {
        if ($request->isNotFilled('success', 'subscription', 'order')) {
            throw new Exception;
        }

        $subscription = Subscription::findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));
        $merchant = $subscription->merchant()->first();

        if (!$order->total_price) {
            $order->update(['payment_status_id' => PaymentStatus::PAID]);
            $order->refresh();
        }

        if ($order->payment_type_id != PaymentType::PAYMAYA_WALLET || $order->isSatisfied()) {
            $request->query->remove('action');

            return $this->redirectToStoreFront(
                $request,
                $subscription,
                $order,
                $merchant,
                $order->isSatisfied()
            );
        }

        if ($order->isPayable()) {
            return $this->payViaWallet(
                $request,
                $subscription,
                $order,
                $merchant
            );
        }

        $subscription->forceFill(['paymaya_link_id' => null])->update();

        $order->forceFill([
            'payment_status_id' => PaymentStatus::FAILED,
            'paymaya_link_id' => null,
            'payment_url' => null
        ])->update();

        return $this->redirectToStoreFront(
            $request,
            $subscription,
            $order,
            $merchant,
            $request->input('success')
        );
    }

    /**
     * Redirect to storefront.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Merchant  $merchant
     * @param  bool  $isSuccess
     *
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToStoreFront(
        Request $request,
        Subscription $subscription,
        Order $order,
        Merchant $merchant,
        $isSuccess
    ) {
        $mainQuery = [
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
            'action' => $request->input('action'),
        ];

        $scheme = app()->isLocal() ? 'http' : 'https';

        $stateUrl = $isSuccess
            ? config('bukopay.url.payment_success')
            : config('bukopay.url.payment_failed');

        $url = "{$scheme}://{$merchant->subdomain}.{$stateUrl}?" . http_build_query(
            $mainQuery + [$isSuccess ? 'success' : 'failed' => 1]
        );

        if ($request->input('isFromFailedPayment')) {
            $url .= "&isFromFailedPayment=1";
        }

        return Redirect::away($url);
    }

    /**
     * Initialize wallet payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Merchant  $merchant
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function payViaWallet(
        Request $request,
        Subscription $subscription,
        Order $order,
        Merchant $merchant
    ) {
        $order = (new PaymentService)->start($order);

        return $this->redirectToStoreFront(
            $request,
            $subscription,
            $order,
            $merchant,
            $order->isPaid()
        );
    }

    /**
     * Handle redirection for card payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleCardVerification($request)
    {
        if ($request->isNotFilled('success', 'subscription', 'order')) {
            throw new Exception;
        }

        $subscription = Subscription::findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));
        $merchant = $subscription->merchant()->first();

        if (!$order->total_price) {
            $order->update([
                'payment_status_id' => $request->input('success')
                    ? PaymentStatus::PAID
                    : PaymentStatus::FAILED
            ]);
        }

        if ($subscription->customer->paymaya_uuid && $order->paymaya_card_token_id) {
            $response = PayMaya::customerCards()->find(
                $subscription->customer->paymaya_uuid,
                $order->paymaya_card_token_id,
                $merchant->paymaya_vault_console_secret_key
                    ?? $merchant->paymaya_vault_secret_key
            );

            if ($response->successful() && $response->json('walletType') === 'VAULTED') {
                $subscription->customer->cards()
                    ->firstOrNew(['card_token_id' => $response->json('cardTokenId')])
                    ->fill([
                        'card_type' => $order->subscription->paymaya_card_type,
                        'masked_pan' => $order->subscription->paymaya_masked_pan,
                    ])
                    ->verify()
                    ->touch();
            }

            if ($response->failed()) {
                $order->subscription()
                    ->where('paymaya_card_token_id', $order->paymaya_card_token_id)
                    ->update([
                        'paymaya_payment_token_id' => null,
                        'paymaya_verification_url' => null,
                        'paymaya_card_token_id' => null,
                        'paymaya_card_type' => null,
                        'paymaya_masked_pan' => null,
                    ]);

                $subscription->orders()
                    ->where('order_status_id', OrderStatus::UNPAID)
                    ->where('paymaya_card_token_id', $order->paymaya_card_token_id)
                    ->update([
                        'paymaya_card_token_id' => null,
                        'paymaya_card_type' => null,
                        'paymaya_masked_pan' => null,
                    ]);

                if ($response->json('code') ===  PayMaya::ERROR_CARD_NOT_FOUND) {
                    $subscription->customer->cards()
                        ->where('card_token_id', $order->paymaya_card_token_id)
                        ->delete();
                }
            }
        }

        return $this->redirectToStoreFront(
            $request,
            $subscription->refresh(),
            $order->refresh(),
            $merchant,
            $request->input('success')
        );
    }

    /**
     * Handle redirection for card payments.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function handleCardPayments($request)
    {
        if ($request->isNotFilled('success', 'merchant', 'subscription', 'order')) {
            throw new Exception;
        }

        $merchant = Merchant::findOrFail($request->input('merchant'));
        $subscription = $merchant->subscriptions()->findOrFail($request->input('subscription'));
        $order = $subscription->orders()->findOrFail($request->input('order'));

        OldPayMaya::setVaultKeys(
            $merchant->paymaya_vault_console_public_key
                ?? $merchant->paymaya_vault_public_key,
            $merchant->paymaya_vault_console_secret_key
                ?? $merchant->paymaya_vault_secret_key
        );

        (new Payment(data_get($order, 'payment_info.payment.id')))
            ->get()
            ->then(function ($payment) use ($order) {
                $order->payment_info = compact('payment');
            })
            ->wait(false);

        $success = data_get($order, 'payment_info.payment.status') === 'PAYMENT_SUCCESS';

        $order->update([
            'payment_status_id' => $success ? PaymentStatus::PAID : PaymentStatus::FAILED,
        ]);

        if ($success) {
            $fundSource = data_get($order, 'payment_info.payment.fundSource');

            $order->subscription->customer->cards()
                ->firstOrNew([
                    'card_token_id' => data_get($fundSource, 'id'),
                ], [
                    'card_type' => data_get($fundSource, 'details.scheme'),
                    'masked_pan' => data_get($fundSource, 'details.last4'),
                ])
                ->verify()
                ->touch();

            $subscription->forceFill([
                'paymaya_verification_url' => null,
            ])->saveQuietly();
        }

        if (!$success) {
            $this->handleCardVerification($request);
        }

        return $this->redirectToStoreFront(
            $request,
            $subscription->refresh(),
            $order->refresh(),
            $merchant,
            $request->input('success')
        );
    }
}
