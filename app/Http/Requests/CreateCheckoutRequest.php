<?php

namespace App\Http\Requests;

use App\Models\Merchant;
use App\Models\MerchantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCheckoutRequest extends FormRequest
{
    /**
     * The user.
     *
     * @var \Illuminate\Foundation\Auth\User
     */
    public $user;

    /**
     * The merchant.
     *
     * @var \App\Models\Merchant
     */
    public $merchant;

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
        $this->user = $this->userOrClient();
        $this->merchant = $this->user instanceof MerchantUser
            ? $this->user->merchant
            : Merchant::find($this->input('merchant_id'));

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
            'merchant_id' => [
                Rule::requiredIf(!$this->user instanceof MerchantUser),
                Rule::exists('merchants', 'id')
                    ->where('is_enabled', true)
                    ->whereNotNull('verified_at')
                    ->withoutTrashed(),
            ],

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
            'success_redirect_url' => 'sometimes|nullable|url',
            'failure_redirect_url' => 'sometimes|nullable|url',

            'other_info.*' => 'sometimes|array:code,value',
            'other_info.*.code' => [
                'required_with:other_info.*.value',
                Rule::in($this->merchant->subscriptionCustomFields->pluck('code')->toArray()),
            ],
            'other_info.*.value' => [
                'required_with:other_info.*.code',
                Rule::forEach(function ($value, $attribute, $data) {
                    $key = preg_replace('/[a-z_]+$/', 'code', $attribute);
                    $code = $data[$key] ?? null;

                    if (!$field = $this->merchant->subscriptionCustomFields->firstWhere('code', $code)) {
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

            'customer.name' => 'sometimes|nullable|string|max:255',
            'customer.email' => 'sometimes|nullable|email|max:255',
            'customer.mobile_number' => 'sometimes|nullable|string|max:255',
            'customer.address' => 'sometimes|nullable|string|max:255',
            'customer.province' => 'sometimes|nullable|string|max:255',
            'customer.city' => 'sometimes|nullable|string|max:255',
            'customer.barangay' => 'sometimes|nullable|string|max:255',
            'customer.country' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'name'),
            ],
            'customer.zip_code' => 'sometimes|nullable|string|max:5',

            'products' => 'required|object_array',
            'products.*.product_id' => [
                'required_with:products.*.product_variant_id',
                'nullable',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $this->merchant->getKey())
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

            'products.*.product_properties.*' => 'sometimes|array:title,value',
            'products.*.product_properties.*.title' => [
                'required_with:products.*.product_properties.*.value',
                Rule::forEach(function ($value, $attribute, $data) {
                    $attribute = substr($attribute, 0, strpos($attribute, '.product_properties'));
                    $productKey = "{$attribute}.product_id";
                    $productId = $data[$productKey] ?? null;

                    if (!$product = $this->merchant->products->firstWhere('id', $productId)) {
                        return [];;
                    }
                    return [
                        Rule::exists('product_properties', 'title')
                            ->where('product_id', $productId)
                            ->where('title', $value),
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
            'products.*.quantity' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $key = preg_replace('/[a-z_]+$/', 'product_id', $attribute);

                    if (is_null($productId = $data[$key] ?? null)) {
                        return;
                    }

                    if (!$product = $this->merchant->products()->find($productId)) {
                        return;
                    }

                    if ($product->quantity_limit && $value > $product->quantity_limit) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'products.*.is_shippable' => [
                'required_without:products.*.product_id',
                'boolean',
            ],
            'products.*.are_multiple_orders_allowed' => [
                'required_without:products.*.product_id',
                'boolean',
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
                return $input->billing_country === 'PH';
            }
        );

        $validator->sometimes(
            'shipping_province',
            Rule::exists('provinces', 'name'),
            function ($input) {
                return $input->shipping_country === 'PH';
            }
        );

        $validator->sometimes(
            'customer.mobile_number',
            'mobile_number',
            function ($input) {
                return data_get($input, 'customer.country') === 'PH';
            }
        );

        $validator->sometimes(
            'customer.mobile_number',
            'mobile_number',
            function ($input) {
                return data_get($input, 'customer.country') === 'PH';
            }
        );
    }
}
