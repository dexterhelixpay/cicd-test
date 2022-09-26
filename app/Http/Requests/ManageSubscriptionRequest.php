<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use App\Models\MerchantUser;
use Illuminate\Validation\Rule;

class ManageSubscriptionRequest extends WithMerchant
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

        return $user instanceof MerchantUser
            ? $user->merchant_id == $this->route('subscription')->merchant_id
            : true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        if (!$this->merchant) {
            $this->merchant = $this->route('subscription')->merchant()->first();
        }

        optional($this->merchant)->load('subscriptionCustomFields');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'payor' => 'sometimes|string|max:255',
            'billing_address' => 'sometimes|string|max:255',
            'billing_barangay' => 'sometimes|string|max:255',
            'billing_city' => 'sometimes|string|max:255',
            'billing_province' => 'sometimes|string|max:255',
            'billing_country' => [
                'sometimes',
                Rule::exists('countries', 'name'),
            ],
            'billing_zip_code' => 'sometimes|string|max:5',

            'recipient' => 'sometimes|nullable|string|max:255',
            'shipping_address' => 'sometimes|nullable|string|max:255',
            'shipping_barangay' => 'sometimes|nullable|string|max:255',
            'shipping_city' => 'sometimes|nullable|string|max:255',
            'shipping_province' => 'sometimes|nullable|string|max:255',
            'shipping_country' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'name'),
            ],
            'shipping_zip_code' => 'sometimes|nullable|string|max:5',

            'reference_id' => 'sometimes|nullable|string|max:255',

            'max_payment_count' => 'sometimes|nullable|integer|min:2',

            'other_info.*' => 'sometimes|array:code,value',
            'other_info.*.code' => [
                'required_with:other_info.*.value',
                Rule::in($this->merchant?->subscriptionCustomFields?->pluck('code')?->toArray() ?? []),
            ],
            'other_info.*.value' => [
                'required_with:other_info.*.code',
                Rule::forEach(function ($value, $attribute, $data) {
                    $key = preg_replace('/[a-z_]+$/', 'code', $attribute);
                    $code = $data[$key] ?? null;

                    if (!$field = $this->merchant?->subscriptionCustomFields?->firstWhere('code', $code)) {
                        return [];
                    }

                    return match ($field->data_type) {
                        'date' => 'date_format:Y-m-d',
                        'number' => 'numeric',
                        'dropdown' => Rule::in($field->dropdown_selection ?? []),
                        'json' => 'array',
                        'datetime' => 'required|date',
                        default => 'string',
                    };
                }),
            ],
        ];
    }
}
