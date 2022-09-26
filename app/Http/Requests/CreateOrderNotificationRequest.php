<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Models\OrderNotification;
use Illuminate\Validation\Rule;

class CreateOrderNotificationRequest extends WithMerchant
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return $this->user ?? $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            'purchase_type' => 'required|array',
            'purchase_type.*' => [
                'distinct',
                Rule::in([
                    OrderNotification::PURCHASE_SINGLE,
                    OrderNotification::PURCHASE_SUBSCRIPTION,
                ]),
            ],
            'subscription_type' => [
                Rule::requiredIf(
                    in_array(OrderNotification::PURCHASE_SUBSCRIPTION, $this->input('purchase_type') ?: [])
                ),
                'nullable',
                'array',
            ],
            'subscription_type.*' => [
                'distinct',
                Rule::in([
                    OrderNotification::SUBSCRIPTION_AUTO_CHARGE,
                    OrderNotification::SUBSCRIPTION_AUTO_REMIND,
                ]),
            ],
            'applicable_orders' => [
                Rule::requiredIf(
                    in_array(OrderNotification::PURCHASE_SUBSCRIPTION, $this->input('purchase_type') ?: [])
                ),
                'nullable',
                'array',
            ],
            'applicable_orders.*' => [
                'distinct',
                Rule::in([
                    OrderNotification::ORDER_FIRST,
                    OrderNotification::ORDER_SUCCEEDING,
                ]),
            ],

            'days_from_billing_date' => 'required|integer|min:-365|max:365',

            'subject' => 'required|string|max:255',
            'headline' => 'required|string|max:255',
            'subheader' => 'required|string|max:255',

            'payment_headline' => 'nullable|string|max:255',
            'payment_instructions' => 'nullable|string|max:255',
            'payment_button_label' => 'nullable|string|max:255',

            'total_amount_label' => 'nullable|string|max:255',

            'payment_instructions_headline' => 'nullable|string|max:255',
            'payment_instructions_subheader' => 'nullable|string|max:255',

            'recurrences' => 'nullable|array',
            'recurrences.*' => [
                'distinct',
                Rule::in([
                    'weekly',
                    'semimonthly',
                    'monthly',
                    'bimonthly',
                    'quarterly',
                    'semiannual',
                    'annually',
                ]),
            ],

            'is_enabled' => 'sometimes|boolean',
        ]);
    }
}
