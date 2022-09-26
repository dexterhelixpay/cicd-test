<?php

namespace App\Http\Requests;

use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$user = $this->userOrClient()) {
            return false;
        }

        if ($user instanceof User) {
            return true;
        }

        if ($user instanceof MerchantUser) {
            return $this->route('order')?->subscription?->merchant_id === $user->merchant_id;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $hasShippableProducts = $this->route('order')->products()
            ->where('is_shippable', true)
            ->exists();

        return [
            'billing_date' => [
                'sometimes',
                'date_format:Y-m-d',
            ],

            'payor' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'billing_address' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'billing_barangay' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'billing_city' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'billing_province' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'billing_country' => [
                'sometimes',
                Rule::exists('countries', 'name'),
            ],
            'billing_zip_code' => [
                'sometimes',
                'string',
                'max:5',
            ],

            'recipient' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:255',
            ],
            'shipping_address' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:255',
            ],
            'shipping_barangay' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:255',
            ],
            'shipping_city' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:255',
            ],
            'shipping_province' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:255',
            ],
            'shipping_country' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                Rule::exists('countries', 'name'),
            ],
            'shipping_zip_code' => [
                'sometimes',
                Rule::when(!$hasShippableProducts, 'nullable'),
                'string',
                'max:5',
            ],

            'shipped_at' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d H:i:s',
            ],
            'fulfilled_at' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d H:i:s',
            ],
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
        $validator->sometimes(
            'billing_province',
            Rule::exists('provinces', 'name'),
            function ($input) {
                return $input->billing_country === 'Philippines';
            }
        );

        $validator->sometimes(
            'shipping_province',
            Rule::exists('provinces', 'name'),
            function ($input) {
                return $input->shipping_country === 'Philippines';
            }
        );
    }
}
