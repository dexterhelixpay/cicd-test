<?php

namespace App\Http\Requests;

use App\Http\Requests\Contracts\WithMerchant;
use Illuminate\Validation\Rule;

class CreateSubscriptionRequest extends WithMerchant
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (bool) $this->userOrClient();
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        optional($this->merchant)->load('subscriptionCustomFields');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            'payment_type_id' => [
                'sometimes',
                'nullable',
                Rule::exists('payment_types', 'id')
                    ->where('is_enabled', true),
            ],

            'payor' => 'sometimes|nullable|string|max:255',
            'billing_address' => 'sometimes|nullable|string|max:255',
            'billing_barangay' => 'sometimes|nullable|string|max:255',
            'billing_city' => 'sometimes|nullable|string|max:255',
            'billing_province' => 'sometimes|nullable|string|max:255',
            'billing_country' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'name'),
            ],
            'billing_zip_code' => 'sometimes|nullable|string|max:5',

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
            'checkout_id' => 'sometimes|nullable|checkout_hash',
            'ignores_inventory' => 'sometimes|boolean',

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

            'customer' => 'required',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'sometimes|nullable|email|max:255',
            'customer.mobile_number' => 'required|string|max:255',
            'customer.address' => 'required|string|max:255',
            'customer.barangay' => 'required|string|max:255',
            'customer.city' => 'required|string|max:255',
            'customer.province' => 'required|string|max:255',
            'customer.country' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'name'),
            ],
            'customer.zip_code' => 'required|string|max:5',
            'customer.other_info' => 'sometimes|array',
            'customer.other_info.*.code' => [
                'required_with:customer.other_info.*.value',
                Rule::exists('custom_fields', 'code')
                    ->when($this->merchant, function ($query, $merchant) {
                        $query->where('merchant_id', $merchant->getKey());
                    }),
            ],
            'customer.other_info.*.value' => 'required_with:customer.other_info.*.code',

            'products' => 'required|object_array',
            'products.*.product_id' => [
                'required_with:products.*.product_variant_id',
                'nullable',
                Rule::exists('products', 'id')
                    ->when($this->merchant, function ($query, $merchant) {
                        $query->where('merchant_id', $merchant->getKey());
                    })
                    ->withoutTrashed(),
            ],
            'products.*.product_variant_id' => [
                'sometimes',
                'nullable',
                Rule::forEach(function ($value, $attribute, $data) {
                    $key = preg_replace('/[a-z_]+$/', 'product_id', $attribute);

                    if (is_null($productId = $data[$key] ?? null)) {
                        return [];
                    }

                    return [
                        Rule::exists('product_variants', 'id')
                            ->where('product_id', $productId),
                    ];
                }),
            ],
            'products.*.title' => [
                'required_without:products.*.product_id',
                'string',
                'max:255',
            ],
            'products.*.description' => 'nullable|string',
            'products.*.images' => 'sometimes|array',
            'products.*.images.*' => 'url',
            'products.*.payment_schedule' => 'required',
            'products.*.payment_schedule.frequency' => [
                'required',
                Rule::in([
                    'interval',
                    'single',
                    'weekly',
                    'semimonthly',
                    'monthly',
                    'bimonthly',
                    'quarterly',
                    'semiannual',
                    'annually',
                ]),
            ],
            'products.*.payment_schedule.unit' => [
                'required_if:products.*.payment_schedule.frequency,interval',
                Rule::in('day', 'week', 'month'),
            ],
            'products.*.payment_schedule.value' => [
                'required_if:products.*.payment_schedule.frequency,interval',
                'integer',
                'min:1',
                'max:31',
            ],
            'products.*.payment_schedule.day_of_week' => 'integer|min:0|max:6',
            'products.*.payment_schedule.day' => 'integer|min:1|max:31',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.is_shippable' => [
                'required_without:products.*.product_id',
                'boolean',
            ],
            'products.*.are_multiple_orders_allowed' => [
                'required_without:products.*.product_id',
                'boolean',
            ],
        ]);
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
                return is_null($input->billing_country)
                    || $input->billing_country === 'Philippines';
            }
        );

        $validator->sometimes(
            'shipping_province',
            Rule::exists('provinces', 'name'),
            function ($input) {
                return is_null($input->shipping_country)
                    || $input->shipping_country === 'Philippines';
            }
        );

        $validator->sometimes(
            'customer.mobile_number',
            'mobile_number',
            function ($input) {
                $country = data_get($input, 'customer.country');

                return is_null($country) || $country === 'Philippines';
            }
        );
    }
}
