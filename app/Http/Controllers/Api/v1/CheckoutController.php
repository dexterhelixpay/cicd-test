<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['logged', 'client']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validateRequest($request, $merchant = $request->userOrClient()->merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $subscription = Arr::only($request->input('data'), 'attributes');

            if ($otherInfo = data_get($subscription, 'attributes.other_info')) {
                data_set(
                    $subscription,
                    'attributes.other_info',
                    collect($otherInfo)
                        ->map(function ($info) use ($merchant) {
                            if (!$field = $merchant->subscriptionCustomFields->firstWhere('code', $info['code'])) {
                                return null;
                            }

                            return Arr::except($field->toArray(), ['created_at', 'updated_at'])
                                + Arr::only($info, 'value');
                        })
                        ->filter()
                        ->toArray()
                );
            }

            $checkout = $merchant->checkouts()
                ->make($request->input('data.attributes'))
                ->forceFill([
                    'subscription' => $subscription,
                    'customer' => $request->input('data.relationships.customer.data'),
                    'products' => collect($request->input('data.relationships.products.data'))
                        ->map(function ($data) use ($merchant) {
                            if (!$product = $merchant->products()->find(data_get($data, 'attributes.product_id'))) {
                                data_set(
                                    $data,
                                    'attributes',
                                    Arr::except($data['attributes'], ['product_id', 'product_variant_id'])
                                );

                                return $data;
                            }

                            if ($variantId = data_get($data, 'attributes.product_variant_id')) {
                                $variant = $product->allVariants()->find($variantId);
                            }

                            if (empty($variant)) {
                                $frequency = data_get($data, 'attributes.payment_schedule.frequency');

                                $variant = $product->allVariants()
                                    ->whereHas('optionValues', function ($query) use ($frequency) {
                                        $query
                                            ->where('value', $frequency)
                                            ->whereHas('option', function ($query) {
                                                $query->where('code', 'recurrence');
                                            });
                                    })
                                    ->first();
                            }

                            if (empty($variant)) {
                                $product->syncDefaultVariant();

                                $variant = $product->defaultVariant()->first();
                            }

                            data_set(
                                $data,
                                'attributes',
                                array_merge(data_get($data, 'attributes', []), [
                                    'product_variant_id' => $variant->getKey(),
                                    'are_multiple_orders_allowed' => $product->are_multiple_orders_allowed,
                                    'is_shippable' => $variant->is_shippable,
                                ])
                            );

                            if (!data_get($data, 'attributes.title')) {
                                data_set($data, 'attributes.title', $product->title);
                            }

                            if (!data_get($data, 'attributes.description')) {
                                data_set($data, 'attributes.description', $product->description);
                            }

                            if (!Arr::has($data, 'attributes.price')) {
                                data_set($data, 'attributes.price', $variant->price);
                            }

                            if (
                                !data_get($data, 'attributes.images')
                                && $product->images()->exists()
                            ) {
                                data_set(
                                    $data,
                                    'attributes.images',
                                    $product->images()->pluck('image_path')
                                );
                            }

                            if ($propeties = data_get($data, 'attributes.product_properties')) {
                                data_set(
                                    $data,
                                    'attributes.product_properties',
                                    collect($propeties)
                                        ->map(function ($property) use ($merchant, $product) {
                                            if (!$productProperty = $product->properties->firstWhere('title', $property['title'])) {
                                                return null;
                                            }

                                            return Arr::except($productProperty->toArray(), ['created_at', 'updated_at'])
                                                + Arr::only($property, 'value');
                                        })
                                        ->filter()
                                        ->toArray()
                                );
                            }
                            return $data;
                        })
                        ->toArray(),
                ]);

            $checkout->save();

            return new CreatedResource($checkout->fresh());
        });
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant)
    {
        $isShippable = collect($request->input('data.relationships.products.data', []))
            ->contains(function ($data) {
                return data_get($data, 'attributes.is_shippable', false);
            });

        $philippines = Country::where('code', 'PH')->first();
        $country = Country::find(
            $request->input('data.relationships.customer.data.attributes.country_id')
        ) ?? $philippines;

        $request->validate([
            'data.attributes' => 'required',
            'data.attributes.payment_type_id' => [
                'sometimes',
                'nullable',
                Rule::exists('payment_types', 'id')
                    ->where('is_enabled', true),
            ],

            'data.attributes.payor' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_address' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_province' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_city' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_barangay' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_country' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_zip_code' => 'sometimes|nullable|string|max:5',

            'data.attributes.shipping_method_id' => [
                Rule::requiredIf($isShippable),
                Rule::exists('shipping_methods', 'id')
                    ->where('merchant_id', $merchant->getKey()),
            ],

            'data.attributes.recipient' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_address' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_province' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_city' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_barangay' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_zip_code' => 'sometimes|nullable|string|max:5',

            'data.attributes.other_info.*' => 'array:code,value',
            'data.attributes.other_info.*.code' => [
                'required_with:data.attributes.other_info.*.value',
                function ($attribute, $value, $fail) use ($merchant) {
                    if (!$merchant->subscriptionCustomFields->firstWhere('code', $value)) {
                        return $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'data.attributes.other_info.*.value' => [
                'required_with:data.attributes.other_info.*.code',
                function ($attribute, $value, $fail) use ($request, $merchant) {
                    $index = explode('.', $attribute)[3];
                    $code = $request->input("data.attributes.other_info.{$index}.code");

                    if (!$field = $merchant->subscriptionCustomFields->firstWhere('code', $code)) {
                        return;
                    }

                    try {
                        switch ($field->data_type) {
                            case 'string':
                                $rules = 'string';
                                break;

                            case 'date':
                                $rules = 'date_format:Y-m-d';
                                break;

                            case 'number':
                                $rules = 'numeric';
                                break;

                            case 'dropdown':
                                $rules = Rule::in($field->dropdown_selection ?? []);
                                break;

                            case 'json':
                                $rules = 'array';
                                break;

                            default:
                                return $fail("The selected {$attribute} is invalid.");
                        }

                        $data = [];

                        Validator::make(data_set($data, $attribute, $value), [$attribute => $rules])->validate();
                    } catch (ValidationException $e) {
                        $fail(Arr::first(Arr::flatten($e->errors())));
                    }
                },
            ],

            'data.attributes.success_redirect_url' => 'url',
            'data.attributes.failure_redirect_url' => 'url',
            'data.attributes.max_payment_count' => 'nullable|integer|min:2',

            'data.relationships.customer.data.attributes.name' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:255',
            ],
            'data.relationships.customer.data.attributes.email' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->filled('data.relationships.customer.data.attributes')
                        && $request->isNotFilled('data.relationships.customer.data.attributes.mobile_number');
                }),
                'nullable',
                'email',
                'max:255',
            ],
            'data.relationships.customer.data.attributes.country_id' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'id'),
            ],
            'data.relationships.customer.data.attributes.mobile_number' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->filled('data.relationships.customer.data.attributes')
                        && $request->isNotFilled('data.relationships.customer.data.attributes.email');
                }),
                'nullable',
                Rule::when($country->is($philippines), 'mobile_number'),
                'max:255',
            ],
            'data.relationships.customer.data.attributes.address' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:255'
            ],
            'data.relationships.customer.data.attributes.province' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:255'
            ],
            'data.relationships.customer.data.attributes.city' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:255'
            ],
            'data.relationships.customer.data.attributes.barangay' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:255'
            ],
            'data.relationships.customer.data.attributes.zip_code' => [
                'required_with:data.relationships.customer.data.attributes',
                'string',
                'max:5'
            ],

            'data.relationships.products.data' => 'required',
            'data.relationships.products.data.*.attributes.product_id' => [
                'required_with:data.relationships.products.data.*.attributes.product_variant_id',
                'nullable',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $merchant->getKey())
                    ->withoutTrashed(),
            ],

            'data.relationships.products.data.*.attributes.product_properties.*' => 'array:title,value',
            'data.relationships.products.data.*.attributes.product_properties.*.title' => [
                'required_with:data.relationships.products.data.*.attributes.product_properties.*.value',
                function ($attribute, $value, $fail) use ($merchant, $request) {
                    if (!$request->filled('data.relationships.products.data.*.attributes.product_id')) {
                        return $fail('Product ID is invalid.');
                    }
                    $productIndex = substr($attribute,0, strpos($attribute, '.attributes'));

                    $product = $merchant
                        ->products->where('id', $request->input("{$productIndex}.attributes.product_id"))
                        ->first();

                    if (!$product->properties->firstWhere('title', $value)) {
                        return $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],

            'data.relationships.products.data.*.attributes.product_variant_id' => [
                'sometimes',
                function ($attribute, $value, $fail) use ($request, $merchant) {
                    $index = explode('.', $attribute)[4];
                    $productId = $request->input("data.relationships.products.data.{$index}.attributes.product_id");

                    if (!$product = $merchant->products()->find($productId)) {
                        return;
                    }

                    if (!$product->allVariants()->find($value)) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'data.relationships.products.data.*.attributes.title' => [
                'required_without:data.relationships.products.data.*.attributes.product_id',
                'string',
                'max:255',
            ],
            'data.relationships.products.data.*.attributes.description' => 'nullable|string',
            'data.relationships.products.data.*.attributes.images' => 'sometimes|nullable|array',
            'data.relationships.products.data.*.attributes.images.*' => 'url',
            'data.relationships.products.data.*.attributes.payment_schedule' => 'required',
            'data.relationships.products.data.*.attributes.payment_schedule.frequency' => [
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
            'data.relationships.products.data.*.attributes.payment_schedule.unit' => [
                'required_if:data.relationships.products.data.*.attributes.payment_schedule.frequency,interval',
                Rule::in('day', 'week', 'month'),
            ],
            'data.relationships.products.data.*.attributes.payment_schedule.value' => [
                'required_if:data.relationships.products.data.*.attributes.payment_schedule.frequency,interval',
                'integer',
                'min:1',
                'max:31',
            ],
            'data.relationships.products.data.*.attributes.payment_schedule.day_of_week' => [
                'integer',
                'min:0',
                'max:6',
            ],
            'data.relationships.products.data.*.attributes.payment_schedule.day' => [
                'integer',
                'min:1',
                'max:31',
            ],
            'data.relationships.products.data.*.attributes.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'data.relationships.products.data.*.attributes.quantity' => [
                'required',
                'numeric',
                function ($attribute, $value, $fail) use ($request, $merchant) {
                    $index = explode('.', $attribute)[4];
                    $productId = $request->input("data.relationships.products.data.{$index}.attributes.product_id");

                    if (!$product = $merchant->products()->find($productId)) {
                        return;
                    }

                    if ($product->quantity_limit && $value > $product->quantity_limit) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'data.relationships.products.data.*.attributes.are_multiple_orders_allowed' => [
                'required_without:data.relationships.products.data.*.attributes.product_id',
                'boolean',
            ],
            'data.relationships.products.data.*.attributes.is_shippable' => [
                'required_without:data.relationships.products.data.*.attributes.product_id',
                'boolean',
            ],
        ]);
    }
}
