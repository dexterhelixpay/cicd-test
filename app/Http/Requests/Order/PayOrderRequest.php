<?php

namespace App\Http\Requests\Order;

use App\Models\Customer;
use App\Models\MerchantUser;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->user()) {
            return false;
        }

        $subscription = $this->route('order')->subscription()->first();

        if ($user instanceof MerchantUser) {
            return $subscription->merchant_id == $user->merchant_id;
        }

        if ($user instanceof Customer) {
            return $subscription->customer_id == $user->getKey();
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            //
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function (Validator $validator) {
            $order = clone $this->route('order');

            if ($order->products()->doesntExist()) {
                $validator->errors()->add('order', "Order doesn't have any products.");
            }

            if (!$order->subscription?->merchant) {
                $validator->errors()->add('order', "Merchant doesn't exist.");
            }

            if (!$order->subscription?->customer) {
                $validator->errors()->add('order', "Customer doesn't exist.");
            }

            if (!$order->payment_schedule && !$order->isInitial()) {
                $validator->errors()->add(
                    'order', 'Payment schedule is missing. Tech action required.'
                );
            }

            if (!in_array($order->order_status_id, [OrderStatus::UNPAID, OrderStatus::FAILED])) {
                $validator->errors()->add('order', 'Order is ' . $order->orderStatus->name . '.');
            }

            if (
                $order->payment_type_id == PaymentType::CARD
                && !$order->subscription->paymaya_card_token_id
            ) {
                $error = 'No card is binded to the subscription.';

                $hasVerifiedCards = $order->subscription
                    ?->customer
                    ?->cards
                    ?->filter(fn ($card) => $card->isVerified())
                    ?->isNotEmpty();

                if ($hasVerifiedCards) {
                    $error .= ' Customer has vaulted cards.';
                }

                $validator->errors()->add('order', $error);
            }

            if (
                $order->payment_type_id == PaymentType::PAYMAYA_WALLET
                && !$order->subscription->paymaya_link_id
            ) {
                $error = 'No wallet is binded to the subscription.';

                $hasVerifiedWallets = $order->subscription
                    ?->customer
                    ?->wallets
                    ?->filter(fn ($card) => $card->isVerified())
                    ?->isNotEmpty();

                if ($hasVerifiedWallets) {
                    $error .= ' Customer has vaulted wallets.';
                }

                $validator->errors()->add('order', $error);
            }
        });
    }
}
