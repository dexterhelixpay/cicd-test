<?php

namespace App\Services;

use App\Facades\Xendit;
use App\Facades\v2\PayMaya;
use App\Libraries\PayMaya\Requests\CardLink;
use App\Libraries\PayMaya\Requests\CardPayment;
use App\Libraries\PayMaya\Requests\Customer as CustomerRequest;
use App\Libraries\PayMaya\Requests\WalletLink;
use App\Libraries\PayMaya\Requests\WalletTransaction;
use App\Libraries\Xendit\CallbackUrl;
use App\Libraries\Xendit\Requests\EWalletCharge as EWalletChargeRequest;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\Subscription;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Cascade the payment info to the subscription's orders.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  array|int|null  $excludeOrderIds
     * @return \App\Models\Subscription
     */
    public function cascadePaymentInfo(Subscription $subscription, $excludeOrderIds = null)
    {
        $subscription->orders()
            ->where('order_status_id', OrderStatus::UNPAID)
            ->when($excludeOrderIds, function ($query, $ids) {
                $query->whereKeyNot($ids);
            })
            ->get()
            ->each(function (Order $order) use ($subscription) {
                $order->forceFill($subscription->only([
                    'payment_type_id',

                    'paymaya_card_token_id',
                    'paymaya_card_type',
                    'paymaya_masked_pan',

                    'paymaya_link_id',

                    'bank_id',
                ]))->save();
            });

        return $subscription->load('orders');
    }

    /**
     * Create a PayMaya record for the given customer.
     *
     * @param  \App\Models\Customer  $customer
     * @param  \App\Models\Order|null  $order
     * @return \App\Models\Customer
     */
    public function createPayMayaCustomer(Customer $customer, ?Order $order = null)
    {
        $merchant = $customer->merchant;
        $vaultKey = $merchant->paymaya_vault_console_secret_key
            ?? $merchant->paymaya_vault_secret_key;

        $nameParts = Str::splitName($customer->name);
        $recipientParts = optional($order)->recipient
            ? Str::splitName($order->recipient)
            : $nameParts;

        $billingCountry = $customer->country?->code ?? 'PH';
        $shippingCountry = $billingCountry;

        if ($order) {
            $billingCountry = Country::query()
                ->where('name', $order->billing_country)
                ->value('code') ?? $billingCountry;

            $shippingCountry = Country::query()
                ->where('name', $order->shipping_country)
                ->value('code') ?? $shippingCountry;
        }

        $request = (new CustomerRequest)
            ->setInfo(
                $nameParts['firstName'],
                $nameParts['lastName'],
                $customer->created_at->toDateString(),
            )
            ->setContact($customer->email, $customer->mobile_number)
            ->setBillingAddress(
                countryCode: $billingCountry,
                line1: optional($order)->billing_address ?? $customer->address,
                line2: optional($order)->billing_barangay ?? $customer->barangay,
                city: optional($order)->billing_city ?? $customer->city,
                state: optional($order)->billing_province ?? $customer->province,
                zipCode: optional($order)->billing_zip_code ?? $customer->zip_code,
            )
            ->setShippingAddress(
                countryCode: $shippingCountry,
                firstName: $recipientParts['firstName'],
                lastName: $recipientParts['lastName'],
                email: $customer->email,
                phone: $customer->mobile_number,
                line1: optional($order)->shipping_address ?? $customer->address,
                line2: optional($order)->shipping_barangay ?? $customer->barangay,
                city: optional($order)->shipping_city ?? $customer->city,
                state: optional($order)->shipping_province ?? $customer->province,
                zipCode: optional($order)->shipping_zip_code ?? $customer->zip_code,
            );

        if ($paymayaUuid = $customer->paymaya_uuid) {
            $response = PayMaya::customers()->find($paymayaUuid, $vaultKey);

            $response->onError(function () use ($customer) {
                $customer->paymaya_uuid = null;
            });

            if ($response->successful()) {
                PayMaya::customers()
                    ->update($paymayaUuid, $request, $vaultKey)
                    ->throw();
            }
        }

        if (! $customer->paymaya_uuid) {
            $response = PayMaya::customers()->create($request, $vaultKey);
            $response->throw();

            $customer->paymaya_uuid = $response->json('id');
        }

        return tap($customer)->save();
    }

    /**
     * Start the payment process.
     *
     * @param  \App\Models\Order  $order
     * @return \App\Models\Order
     */
    public function start(Order $order)
    {
        if (!$order->total_price && $order->order_status_id != OrderStatus::PAID) {
            $order->paymentStatus()->associate(PaymentStatus::PAID);

            return tap($order)->save();
        }

        if (!$order->hasPayableStatus()) {
            return $order;
        }

        $order
            ->forceFill([
                'order_status_id' => OrderStatus::UNPAID,
                'payment_status_id' => PaymentStatus::PENDING,
                'payment_attempts' => ($order->payment_attempts ?: 0) + 1,
                'payment_attempted_at' => now()->toDateTimeString(),
                'has_payment_lapsed' => false,
            ])
            ->syncOriginalAttribute('order_status_id')
            ->paymentInitiator()
            ->associate(request()->userOrClient() ?: $order->subscription->customer);

        if (!Str::startsWith(request()->userAgent() ?: '', 'Mozilla/')) {
            $order
                ->forceFill([
                    'auto_payment_attempts' => ($order->auto_payment_attempts ?: 0) + 1,
                ])
                ->paymentInitiator()
                ->dissociate();
        }

        $this->resetPaymentInfo($order);

        return match ((int) $order->payment_type_id) {
            PaymentType::GCASH => $this->chargeUsingXendit($order),
            PaymentType::GRABPAY => $this->chargeUsingXendit($order, false),
            PaymentType::CARD => $this->payUsingCard($order),
            PaymentType::PAYMAYA_WALLET => $this->payWithPayMayaWallet($order),
            default => $order,
        };
    }

    /**
     * Link the given card to the related customer.
     *
     * @param  \App\Models\Order  $order
     * @param  string  $paymentToken
     * @param  string  $cardType
     * @return \App\Models\CustomerCard|\Illuminate\Http\Client\RequestException
     */
    public function linkCardToCustomer(
        Order $order,
        string $paymentToken,
        string $cardType = null,
        bool $isInitial = true
    ) {
        $merchant = $order->subscription->merchant;
        $customer = $order->subscription->customer;

        $query = ['action' => $isInitial ? 'verify' : 'update_card'];
        $request = (new CardLink)
            ->setToken($paymentToken)
            ->redirectOnSuccess($this->getRedirectUrl($order, true, 'card_verification', $query))
            ->redirectOnFailure($this->getRedirectUrl($order, false, 'card_verification', $query))
            ->withPaymentFacilitator(
                smi: smi($cardType),
                smn: mb_substr(
                    preg_replace('/\s/', '', $merchant->name), 0, 9
                ),
                mci: config('services.paymaya.metadata.mci'),
                mco: config('services.paymaya.metadata.mco'),
                mpc: config('services.paymaya.metadata.mpc'),
            );

        try {
            $response = PayMaya::customerCards()->link(
                $customer->paymaya_uuid,
                $request,
                $merchant->paymaya_vault_console_secret_key ?? $merchant->paymaya_vault_secret_key
            );

            $response->throw();
        } catch (RequestException $e) {
            return $e;
        }

        $order->subscription->forceFill([
            'paymaya_payment_token_id' => null,
            'paymaya_verification_url' => $response->json('verificationUrl'),
            'paymaya_card_token_id' => $response->json('cardTokenId'),
            'paymaya_card_type' => $response->json('cardType'),
            'paymaya_masked_pan' => $response->json('maskedPan'),
        ])->save();

        $order->forceFill($order->subscription->only([
            'paymaya_card_token_id',
            'paymaya_card_type',
            'paymaya_masked_pan',
        ]))->save();

        $card = $customer->cards()
            ->firstOrNew(['card_token_id' => $order->subscription->paymaya_card_token_id])
            ->fill([
                'card_type' => $order->subscription->paymaya_card_type,
                'masked_pan' => $order->subscription->paymaya_masked_pan,
            ]);

        return tap($card)->touch();
    }

    /**
     * Execute linking of PayMaya wallet to the given order.
     *
     * @param  \App\Models\Order  $order
     * @param  bool  $payAfterLinking
     * @return \App\Models\Order
     */
    public function linkPayMayaWallet(Order $order, $payAfterLinking = true)
    {
        $customer = $order->subscription->customer;
        $merchant = $order->subscription->merchant;

        $order->forceFill($order->subscription->only('paymaya_link_id'));

        if ($order->paymaya_link_id) {
            $order->subscription->paymaya_verification_url = null;

            $response = PayMaya::wallets()->find(
                $order->paymaya_link_id,
                $merchant->paymaya_pwp_console_secret_key ?? $merchant->paymaya_pwp_secret_key
            );

            if ($response->successful()) {
                $name = collect([
                    $response->json('customer.firstName'),
                    $response->json('customer.lastName'),
                ])->filter()->join(' ');

                $order->subscription->forceFill([
                    'paymaya_wallet_customer_name' => $name,
                    'paymaya_wallet_mobile_number' => $response->json('customer.contact.phone'),
                ]);

                if ($response->json('card.state') === 'VERIFIED') {
                    $customer->wallets()
                        ->firstOrNew(['link_id' => $order->paymaya_link_id])
                        ->fill([
                            'name' => $order->subscription->paymaya_wallet_customer_name,
                            'mobile_number' => $order->subscription->paymaya_wallet_mobile_number,
                        ])
                        ->verify()
                        ->save();
                }
            } else {
                $order->subscription->forceFill([
                    'paymaya_wallet_customer_name' => null,
                    'paymaya_wallet_mobile_number' => null,
                ]);

                $customer->wallets()
                    ->where('link_id', $order->paymaya_link_id)
                    ->delete();

                $order->forceFill(['paymaya_link_id' => null])->save();
            }

            $order->subscription->save();
        }

        if (!$order->paymaya_link_id) {
            $query = [
                'isFromFailedPayment' => intval(
                    $order->getOriginal('payment_status_id') == PaymentStatus::FAILED
                ),
            ];

            if ($payAfterLinking) {
                $type = 'wallet_payment';
            } else {
                $type = 'wallet_verification';
                $query['action'] = 'verify_wallet';
            }

            $request = (new WalletLink)
                ->redirectOnSuccess($this->getRedirectUrl($order, true, $type, $query))
                ->redirectOnFailure($this->getRedirectUrl($order, false, $type, $query));

            $response = PayMaya::wallets()->create(
                $request,
                $merchant->paymaya_pwp_console_public_key ?? $merchant->paymaya_pwp_public_key
            );

            $response->onError(function (Response $response) use ($order) {
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_url' => null,
                    'payment_info' => [
                        'error' => [
                            'uri' => (string) $response->effectiveUri(),
                            'status' => $response->status(),
                            'reason' => $response->reason(),
                            'body' => $response->json(),
                        ],
                    ],
                ]);
            });

            if ($response->successful()) {
                $order->subscription->forceFill([
                    'paymaya_link_id' => $response->json('linkId'),
                    'paymaya_wallet_customer_name' => null,
                    'paymaya_wallet_mobile_number' => null,
                    'paymaya_verification_url' => $response->json('redirectUrl'),
                ])->save();
            }
        }

        return $order;
    }

    /**
     * Charge the given order using customer's card.
     *
     * @param  \App\Models\Order  $order
     * @return \App\Models\Order
     */
    public function payUsingCard(Order $order)
    {
        $customer = $order->subscription->customer;
        $merchant = $order->subscription->merchant;

        $this->createPayMayaCustomer($customer, $order);

        if ($paymentTokenId = $order->subscription->paymaya_payment_token_id) {
            $card = $this->linkCardToCustomer(
                $order,
                $paymentTokenId,
                $order->subscription->paymaya_card_type,
            );

            if ($card instanceof RequestException) {
                if (! $order->subscription->paymaya_card_token_id) {
                    $order->forceFill([
                        'payment_status_id' => PaymentStatus::FAILED,
                        'payment_info' => [
                            'error' => [
                                'uri' => (string) $card->response->effectiveUri(),
                                'status' => $card->response->status(),
                                'reason' => $card->response->reason(),
                                'body' => $card->response->json(),
                            ],
                        ],
                    ]);

                    $this->resetPaymentInfo(subscription: $order->subscription)->save();

                    return tap($order)->save();
                }

                $order->subscription->forceFill([
                    'paymaya_payment_token_id' => null,
                    'paymaya_verification_url' => null,
                ])->save();
            }
        }

        $order->forceFill(
            $order->subscription->only([
                'paymaya_card_token_id',
                'paymaya_card_type',
                'paymaya_masked_pan',
            ])
        );

        $query = [
            'isFromFailedPayment' => intval(
                $order->getOriginal('payment_status_id') == PaymentStatus::FAILED
            ),
        ];

        $request = (new CardPayment)
            ->setTotalAmount($order->total_price)
            ->redirectOnSuccess($this->getRedirectUrl($order, true, 'card_payment', $query))
            ->redirectOnFailure($this->getRedirectUrl($order, false, 'card_payment', $query))
            ->withPaymentFacilitator(
                smi: smi($order->paymaya_card_type),
                smn: mb_substr(
                    preg_replace('/\s/', '', $merchant->name), 0, 9
                ),
                mci: config('services.paymaya.metadata.mci'),
                mco: config('services.paymaya.metadata.mco'),
                mpc: config('services.paymaya.metadata.mpc'),
            );

        $response = PayMaya::customerCards()->pay(
            $customer->paymaya_uuid,
            $order->paymaya_card_token_id ?? 'INVALID_CARD_TOKEN',
            $request,
            $merchant->paymaya_vault_console_secret_key ?? $merchant->paymaya_vault_secret_key
        );

        if ($response->successful()) {
            $order->payment_info = ['payment' => $response->json()];

            switch ($response->json('status')) {
                case PayMaya::STATUS_PAYMENT_SUCCESS:
                    $order->payment_status_id = PaymentStatus::PAID;
                    break;

                case PayMaya::STATUS_FOR_AUTHENTICATION:
                    $order->payment_url = $response->json('verificationUrl');
                    break;

                case PayMaya::STATUS_PAYMENT_FAILED:
                case PayMaya::STATUS_PAYMENT_EXPIRED:
                    $order->payment_status_id = PaymentStatus::FAILED;
                    break;
            }
        } else {
            $order->forceFill([
                'payment_status_id' => PaymentStatus::FAILED,
                'payment_info' => [
                    'error' => [
                        'uri' => (string) $response->effectiveUri(),
                        'status' => $response->status(),
                        'reason' => $response->reason(),
                        'body' => $response->json(),
                    ],
                ],
            ]);
        }

        return tap($order)->save();
    }

    /**
     * Charge the given order using their PayMaya wallet.
     *
     * @param  \App\Models\Order  $order
     * @return \App\Models\Order
     */
    public function payWithPayMayaWallet(Order $order)
    {
        $this->linkPayMayaWallet($order);

        $merchant = $order->subscription->merchant;

        if ($order->total_price && $order->paymaya_link_id) {
            $request = (new WalletTransaction)
                ->setTotalAmount($order->total_price)
                ->setRrn($order->obfuscateKey());

            $response = PayMaya::wallets()->executePayment(
                $order->paymaya_link_id,
                $request,
                $merchant->paymaya_pwp_console_secret_key ?? $merchant->paymaya_pwp_secret_key
            );

            if ($response->successful()) {
                $order->payment_info = ['payment' => $response->json()];

                switch ($response->json('status')) {
                    case PayMaya::STATUS_PAYMENT_SUCCESS:
                        $order->payment_status_id = PaymentStatus::PAID;
                        break;

                    case PayMaya::STATUS_PAYMENT_EXPIRED:
                    case PayMaya::STATUS_PAYMENT_FAILED:
                        $order->payment_status_id = PaymentStatus::FAILED;
                        break;

                    default:
                        //
                }
            } else {
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_url' => null,
                    'payment_info' => [
                        'error' => [
                            'uri' => (string) $response->effectiveUri(),
                            'status' => $response->status(),
                            'reason' => $response->reason(),
                            'body' => $response->json(),
                        ],
                    ],
                ]);
            }
        }

        $order->save();

        return $order;
    }

    /**
     * Charge the given order using Xendit.
     *
     * @param  \App\Models\Order  $order
     * @param  bool  $isGcash
     * @return \App\Models\Order
     */
    public function chargeUsingXendit(Order $order, bool $isGcash = true)
    {
        $account = $order->subscription->merchant->xenditAccount;

        if (!$account->callback_token) {
            $response = Xendit::callbackUrls()->set(
                CallbackUrl::TYPE_EWALLET,
                route('api.v1.payments.xendit.events'),
                $account->xendit_account_id
            );

            if ($response->successful()) {
                $account->callback_token = $response->json('callback_token');
                $account->save();
            }
        }

        if (!$order->total_price) {
            $order->paymentStatus()->associate(PaymentStatus::PAID)->save();

            return $order;
        }

        $request = (new EWalletChargeRequest($order->total_price, 'PHP', $order->getKey()))
            ->forUser($account->xendit_account_id)
            ->redirectOnSuccess($this->getRedirectUrl($order))
            ->redirectOnFailure($this->getRedirectUrl($order, false));

        if ($account->xendit_fee_rule_id && $account->hasMetThreshold()) {
            $request->withFeeRule($account->xendit_fee_rule_id);
        }

        if ($isGcash) {
            $request->gCash();
        } else {
            $request->grabPay();
        }

        ($response = Xendit::eWalletCharges()->create($request))
            ->onError(function (Response $response) use ($order) {
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::FAILED,
                    'payment_url' => null,
                    'payment_info' => [
                        'error' => [
                            'uri' => (string) $response->effectiveUri(),
                            'status' => $response->status(),
                            'reason' => $response->reason(),
                            'body' => $response->json(),
                        ],
                    ],
                ]);
            });

        if ($response->successful()) {
            $order->forceFill([
                'payment_info' => ['charge' => $response->json()],
                'payment_url' => collect($response->json('actions'))->filter()->first(),
            ]);
        }

        $order->save();

        return $order;
    }

    /**
     * Get the redirect URL for the given order.
     *
     * @param  \App\Models\Order  $order
     * @param  bool  $success
     * @param  string  $type
     * @param  array  $query
     * @return string
     */
    public function getRedirectUrl(
        Order $order,
        bool $success = true,
        string $type = 'payment',
        array $query = []
    ) {
        switch ((int) $order->payment_type_id) {
            case PaymentType::GCASH:
            case PaymentType::GRABPAY:
                $route = 'api.v1.payments.xendit.redirect';
                break;

            case PaymentType::BANK_TRANSFER:
                $route = 'api.v1.payments.brankas.redirect';
                break;

            case PaymentType::CARD:
            case PaymentType::PAYMAYA_WALLET:
            default:
                $route = 'api.v1.payments.paymaya.redirect';
                break;
        }

        return URL::signedRoute($route, array_merge([
            'success' => (int) $success,
            'type' => $type,
            'order' => $order->getKey(),
            'subscription' => $order->subscription->getKey(),
            'merchant' => $order->subscription->merchant_id,
        ], $query));
    }

    /**
     * Reset the payment info of the given order/subscription.
     *
     * @param  \App\Models\Order|null  $order
     * @param  \App\Models\Subscription|null  $subscription
     * @return \App\Models\Order|\App\Models\Subscription|null
     */
    public function resetPaymentInfo(?Order $order = null, ?Subscription $subscription = null)
    {
        if ($order) {
            $order->forceFill([
                'payment_url' => null,
                'payment_info' => null,

                // Card
                'paymaya_card_token_id' => null,
                'paymaya_card_type' => null,
                'paymaya_masked_pan' => null,

                // Wallet
                'paymaya_link_id' => null,

                // Bank
                'bank_id' => null,
                'transaction_id' => null,
            ]);
        }

        if ($subscription) {
            $subscription->forceFill([
                // Card
                'paymaya_payment_token_id' => null,
                'paymaya_card_token_id' => null,
                'paymaya_card_type' => null,
                'paymaya_masked_pan' => null,
                'paymaya_verification_url' => null,

                // Wallet
                'paymaya_link_id' => null,
                'paymaya_wallet_customer_name' => null,
                'paymaya_wallet_mobile_number' => null,

                // Bank
                'bank_id' => null,
                'brankas_masked_pan' => null,
            ]);
        }

        return $order ?? $subscription;
    }
}
