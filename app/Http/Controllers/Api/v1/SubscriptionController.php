<?php

namespace App\Http\Controllers\Api\v1;

use Throwable;
use App\Exports\Orders;
use App\Models\Country;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\PaymentType;
use Illuminate\Support\Arr;
use App\Models\MerchantUser;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\PaymentStatus;
use App\Exports\ShopifyOrders;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;
use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use App\Notifications\PaymentReminder;
use App\Exceptions\BadRequestException;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\ResourceCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\UnauthorizedException;
use App\Notifications\NewSubscriberEmailNotification;
use App\Services\ProductService;

class SubscriptionController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['lock:30,30', 'logged'])->only('store');
        $this->middleware('authorize')->only('downloadTemplate');
        $this->middleware('auth.client:customer,merchant,user');
        $this->middleware('permission:CP: Merchants - Edit|MC: Subscriptions')->except('cancel');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->userOrClient();

        $subscriptions = QueryBuilder::for(Subscription::class)
            ->when($user instanceof MerchantUser, function ($query) use ($user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($subscriptions);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\ProductService  $productService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, ProductService $productService)
    {
        $merchant = $this->validateRequest($request);

        $products = collect($request->input('data.relationships.products.data'))
            ->pluck('attributes');

        if (!$ignoresInventory = $request->input('data.attributes.ignores_inventory', false)) {
            $productService->checkStocks($merchant, $products);
        }

        return DB::transaction(function () use ($request, $productService, $merchant, $products, $ignoresInventory) {
            $user = $request->userOrClient();
            $requestFrom = $request->from();

            $subscription = $merchant->subscriptions()
                ->make(Arr::except($request->input('data.attributes', []), 'other_info'))
                ->forceFill([
                    'is_console_booking' => $requestFrom === 'merchant',
                    'is_api_booking' => $requestFrom === 'client',
                ]);

            if ($user instanceof Customer) {
                $customer = $user;
            } elseif ($request->input('data.relationships.customer.data.id')) {
                $customer = Customer::find(
                    $request->input('data.relationships.customer.data.id')
                );
            }

            if (empty($customer)) {
                $customer = $merchant->customers()->create(
                    $request->input('data.relationships.customer.data.attributes')
                );
            }

            if ($request->filled('data.relationships.customer.data.attributes.name')) {
                $customer->fill([
                    'name' => $request->input('data.relationships.customer.data.attributes.name'),
                ]);
            }

            if ($request->filled('data.relationships.customer.data.attributes.other_info')) {
                $otherInfo = collect(
                    $request->input('data.relationships.customer.data.attributes.other_info', [])
                )->mapWithKeys(function ($info) {
                    return [$info['code'] => $info['value']];
                })->toArray();

                $customer->fill(['other_info' => $otherInfo]);
            }

            if (
                !$customer->address
                && $request->filled('data.relationships.customer.data.attributes.address')
            ) {
                $attributes = $request->input('data.relationships.customer.data.attributes');

                $customer->fill(Arr::only($attributes, [
                    'email',
                    'address',
                    'barangay',
                    'city',
                    'province',
                    'zip_code',
                    'country_name',
                ]));
            }

            $customer->save();

            $subscription->customer()->associate($customer);

            if ($request->isNotFilled('data.attributes.payor')) {
                $subscription->fill([
                    'payor' => $customer->name,
                    'billing_address' => $customer->address,
                    'billing_province' => $customer->province,
                    'billing_city' => $customer->city,
                    'billing_barangay' => $customer->barangay,
                    'billing_zip_code' => $customer->zip_code,
                    'billing_country' => $customer->country_name,
                ]);
            }

            $hasShippableProducts = $products->some(function ($product) {
                return data_get($product, 'is_shippable', false);
            });

            if (
                $hasShippableProducts
                && $request->isNotFilled('data.attributes.recipient')
            ) {
                $subscription->fill([
                    'recipient' => $customer->name,
                    'shipping_address' => $customer->address,
                    'shipping_province' => $customer->province,
                    'shipping_city' => $customer->city,
                    'shipping_barangay' => $customer->barangay,
                    'shipping_zip_code' => $customer->zip_code,
                    'shipping_country' => $customer->country_name,
                ]);
            }

            if ($request->filled('data.attributes.max_payment_count')) {
                $subscription->max_payment_count = $request->input('data.attributes.max_payment_count');
            }

            if ($checkoutId = $request->input('data.attributes.checkout_id')) {
                [$checkoutId] = Hashids::connection('checkout')->decode($checkoutId);
                $checkout = Checkout::findOrFail($checkoutId);

                $subscription
                    ->forceFill($checkout->only([
                        'max_payment_count',
                        'success_redirect_url',
                        'failure_redirect_url',
                    ]))
                    ->setAttribute('is_checkout_booking', true);

                $checkout->delete();
            }

            if ($otherInfo = $request->input('data.attributes.other_info')) {
                $subscription->other_info = collect($otherInfo)
                    ->map(function ($info) use ($merchant) {
                        if (!$field = $merchant->subscriptionCustomFields->firstWhere('code', $info['code'])) {
                            return null;
                        }

                        return Arr::except($field->toArray(), ['created_at', 'updated_at'])
                            + Arr::only($info, 'value');
                    })
                    ->filter()
                    ->toArray();
            }

            if (
                $user instanceof Customer
                && $request->filled('data.attributes.customer_card_id')
                && ($card = $user->cards()->find($request->input('data.attributes.customer_card_id')))
            ) {
                $subscription->forceFill([
                    'paymaya_card_token_id' => $card->card_token_id,
                    'paymaya_card_type' => $card->card_type,
                    'paymaya_masked_pan' => $card->masked_pan,
                ]);
            } elseif ($request->filled('data.attributes.paymaya_card_token_id')) {
                $subscription->forceFill([
                    'paymaya_card_token_id' => $request->input('data.attributes.paymaya_card_token_id'),
                    'paymaya_card_type' => $request->input('data.attributes.paymaya_card_type'),
                    'paymaya_masked_pan' => $request->input('data.attributes.paymaya_masked_pan'),
                ]);
            }

            $subscription->save();

            $products->each(function ($product) use ($subscription) {
                $subscription->products()->make($product)->setTotalPrice()->save();
            });

            if (!$ignoresInventory) {
                $productService->takeStocks(
                    $merchant, $subscription->products()->get()->toArray()
                );
            }

            $subscription
                ->setTotalPrice()
                ->createInitialOrders();

            if ($user instanceof Customer) {
                $subscription->initializePayment();
            }

            $order = $subscription->initialOrder()->first();

            if (!$order->total_price) {
                $order->update(['payment_status_id' => PaymentStatus::PAID]);
            } elseif ($user instanceof MerchantUser) {
                if ($subscription->created_at->isSameDay($order->billing_date)) {
                    $subscription->notifyCustomer(
                        'payment',
                        in_array($merchant->getKey(), setting('CustomMerchants', []))
                    );
                } else {
                    $this->sendPaymentReminder($subscription, $order);
                }
            }

            return new CreatedResource(
                $subscription->fresh('customer', 'initialOrder', 'products')
            );
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Subscription $subscription)
    {
        $this->authorizeRequest($request, $subscription);
        $this->validateRequest($request, $subscription);

        return DB::transaction(function () use ($request, $subscription) {
            $data = Arr::only($request->input('data.attributes'), [
                'payor',
                'billing_address',
                'billing_province',
                'billing_city',
                'billing_barangay',
                'billing_zip_code',

                'recipient',
                'shipping_address',
                'shipping_province',
                'shipping_city',
                'shipping_barangay',
                'shipping_zip_code',

                'max_payment_count',

                'reference_id',
            ]);

            $subscription->update($data);

            return new Resource($subscription->fresh());
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription|null  $subscription
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $subscription = null)
    {
        if (
            $request->isFromMerchant()
            && $subscription
            && $subscription->merchant_id != $request->userOrClient()->merchant_id
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Validate the request and return the detected merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription|null  $subscription
     * @return \App\Models\Merchant
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $subscription = null)
    {
        if ($subscription) {
            $request->validate([
                'data.attributes.payor' => 'sometimes|required|string|max:255',
                'data.attributes.billing_address' => 'sometimes|required|string|max:255',
                'data.attributes.billing_province' => 'sometimes|required|string|max:255',
                'data.attributes.billing_city' => 'sometimes|required|string|max:255',
                'data.attributes.billing_barangay' => 'sometimes|required|string|max:255',
                'data.attributes.billing_zip_code' => 'sometimes|required|string|max:5',

                'data.attributes.recipient' => 'sometimes|nullable|string|max:255',
                'data.attributes.shipping_address' => 'sometimes|nullable|string|max:255',
                'data.attributes.shipping_province' => 'sometimes|nullable|string|max:255',
                'data.attributes.shipping_city' => 'sometimes|nullable|string|max:255',
                'data.attributes.shipping_barangay' => 'sometimes|nullable|string|max:255',
                'data.attributes.shipping_zip_code' => 'sometimes|nullable|string|max:5',

                'data.attributes.reference_id' => 'sometimes|nullable|string|max:255',
            ]);

            return $subscription->merchant()->first();
        }

        $user = $request->userOrClient();

        if ($user instanceof MerchantUser) {
            $merchant = $user->merchant;
        } else {
            $request->validate([
                'data.attributes.merchant_id' => [
                    'required',
                    Rule::exists('merchants', 'id')
                        ->where('is_enabled', true)
                        ->whereNotNull('verified_at')
                        ->withoutTrashed(),
                ],
            ]);

            $merchant = Merchant::find($request->input('data.attributes.merchant_id'));
        }

        $philippines = Country::where('name', 'Philippines')->first();

        $hasCard = setting('IsCcPaymentEnabled', true);
        $hasWallet = setting('IsPaymayaWalletEnabled', true);

        if ($user instanceof MerchantUser) {
            $hasCard &= $user->merchant->hasVaultKeys();
            $hasWallet &= $user->merchant->hasPwpKeys();
        }

        $request->validate([
            'data.attributes.payment_type_id' => [
                'nullable',
                Rule::exists('payment_types', 'id')
                    ->where('is_enabled', true)
                    ->when(!setting('IsDigitalWalletPaymentEnabled', true), function ($query) {
                        $query->whereNotIn('id', [PaymentType::GCASH, PaymentType::GRABPAY]);
                    })
                    ->when(!$hasCard, function ($query) {
                        $query->where('id', '<>', PaymentType::CARD);
                    })
                    ->when(!setting('IsBankTransferEnabled', true), function ($query) {
                        $query->where('id', '<>', PaymentType::BANK_TRANSFER);
                    })
                    ->when(!$hasWallet, function ($query) {
                        $query->where('id', '<>', PaymentType::PAYMAYA_WALLET);
                    }),
            ],

            'data.attributes.payor' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_address' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_province' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_city' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_barangay' => 'sometimes|nullable|string|max:255',
            'data.attributes.billing_zip_code' => 'sometimes|nullable|string|max:5',

            'data.attributes.shipping_method_id' => [
                'sometimes',
                Rule::exists('shipping_methods', 'id')
                    ->where('merchant_id', $merchant->getKey()),
            ],

            'data.attributes.recipient' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_address' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_province' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_city' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_barangay' => 'sometimes|nullable|string|max:255',
            'data.attributes.shipping_zip_code' => 'sometimes|nullable|string|max:5',

            'data.attributes.max_payment_count' => 'sometimes|nullable|integer|min:2',

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

            'data.relationships.customer.data.attributes.name' => 'required|string|max:255',
            'data.relationships.customer.data.attributes.email' => [
                Rule::requiredIf(!$request->input('data.relationships.customer.data.attributes.mobile_number')),
                'required_without:data.relationships.customer.data.attributes.mobile_number',
                'nullable',
                'email',
                'max:255',
            ],
            'data.relationships.customer.data.attributes.mobile_number' => [
                Rule::requiredIf(!$request->input('data.relationships.customer.data.attributes.email')),
                'required_without:data.relationships.customer.data.attributes.email',
                'nullable',
                Rule::when(
                    $request->input('data.relationships.customer.data.attributes.country_id') == $philippines->getKey()
                    && $request->filled('data.relationships.customer.data.attributes.mobile_number'),
                    ['mobile_number']
                )
            ],
            'data.relationships.customer.data.attributes.country_id' => [
                'sometimes',
                'nullable',
                Rule::exists('countries', 'id'),
            ],
            'data.relationships.customer.data.attributes.address' => 'required|string|max:255',
            'data.relationships.customer.data.attributes.province' => 'required|string|max:255',
            'data.relationships.customer.data.attributes.city' => 'required|string|max:255',
            'data.relationships.customer.data.attributes.barangay' => 'required|string|max:255',
            'data.relationships.customer.data.attributes.zip_code' => 'required|string|max:5',
            'data.relationships.customer.data.attributes.other_info' => 'array',
            'data.relationships.customer.data.attributes.other_info.*.code' => [
                'required_with:data.relationships.customer.data.attributes.other_info.*.value',
                Rule::exists('custom_fields', 'code')
                    ->where('merchant_id', $merchant->getKey()),
            ],
            'data.relationships.customer.data.attributes.other_info.*.value' => [
                'required_with:data.relationships.customer.data.attributes.other_info.*.code',
            ],

            'data.relationships.products.data' => 'required',
            'data.relationships.products.data.*.attributes.product_id' => [
                'sometimes',
                'nullable',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $merchant->getKey())
                    ->withoutTrashed()
            ],
            'data.relationships.products.data.*.attributes.title' => 'sometimes|nullable|string|max:255',
            'data.relationships.products.data.*.attributes.description' => 'sometimes|nullable|string',
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
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:6',
            ],
            'data.relationships.products.data.*.attributes.payment_schedule.day' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                'max:31',
            ],
            'data.relationships.products.data.*.attributes.price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],
            'data.relationships.products.data.*.attributes.quantity' => 'required|numeric|min:1',
            'data.relationships.products.data.*.attributes.are_multiple_orders_allowed' => [
                'required_without:data.relationships.products.data.*.attributes.product_id',
                'boolean',
            ],
            'data.relationships.products.data.*.attributes.is_shippable' => [
                'required_without:data.relationships.products.data.*.attributes.product_id',
                'boolean',
            ],
        ]);

        return $merchant;
    }

    /**
     * Send a payment reminder to the customer.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function sendPaymentReminder(Subscription $subscription, $order)
    {
        $merchant = $subscription->merchant;
        $customer = $subscription->customer;
        $orderProduct  = $order->products->first();

        $month = Carbon::parse($order->billing_date)->format('F');
        $day = ordinal_number(Carbon::parse($order->billing_date)->format('j'));

        $billingDate = "{$month} {$day}";

        $hasOrderSummary = $subscription->is_console_booking
            && are_all_single_recurrence($subscription->products);

        $initialOrder = $subscription->initialOrder()->first();
        $isInitialOrder = $initialOrder->is($order);
        $startOrContinue = $isInitialOrder ? 'Start' : 'Continue';

        if ($customer->email) {
            $title = $merchant->console_created_email_headline_text
                ?? "{$startOrContinue} your {$merchant->subscription_term_singular} with {$merchant->name}";

            $subheader = $merchant->console_created_email_subheader_text
                ?? "Please enter your payment details to activate your {$merchant->subscription_term_singular}.";

            $subtitle = count($order->attachments) > 0
                ? "{$subheader}. See attached file for full invoice."
                : "{$subheader}";

            $options = [
                'title' => $title,
                'subtitle' => $subtitle,
                'payment_headline' => replace_placeholders('Payment is due on {billingDate}', $order),
                'payment_instructions' => replace_placeholders('Please pay on or before {billingDate}', $order),
                'payment_button_label' => pay_button_text($subscription, $order->id),
                'total_amount_label' => 'Total Amount',
                'payment_instructions_headline' => replace_placeholders('Payment is due on {billingDate}', $order),
                'payment_instructions_subheader' => replace_placeholders('Please pay on or before {billingDate}', $order),
                'type' => 'today',
                'next_payment_subtitle' => "Your payment is due on {$billingDate}",
                'subject' => "Payment Due - {$billingDate}",
                'has_order_summary' => $hasOrderSummary,
                'has_pay_button' => false,
                'subscription_status_title' => '',
                'is_console_created_subscription' => true
            ];

            $customer->notify(
                new PaymentReminder(
                    $subscription,
                    $merchant,
                    $orderProduct,
                    $order,
                    $options
                )
            );

            $subscription->messageCustomer($customer, 'payment', $order);
        }
    }

    /**
     * Send new subscriber email notification to merchants.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    protected function sendNewSubscriberNotification($subscription)
    {
        $merchant = $subscription->merchant;

        $merchant->users()
            ->get()
            ->each(function(MerchantUser $user) use ($subscription, $merchant) {
                if (
                    !$user->email
                    || !$merchant->is_new_subscriber_email_enabled
                    || !$user->is_enabled
                    || !$user->email_verified_at
                ) return;

                $user->notify(new NewSubscriberEmailNotification($merchant, $subscription));
            });

        $products = formatProducts($subscription->products()->get());

        $merchant->sendViberNotification(
            "You have a new subscriber {$subscription->customer->name} for {$products}."
        );
    }

    /**
     * Import the subscriptions for the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadTemplate(Request $request)
    {
        $merchant = Merchant::find($request->query('merchant'));

        $export = optional($merchant)->hasShopifyAccount()
            ? new ShopifyOrders
            : new Orders;

        return $export->download('Import Template.xlsx');
    }

    /**
     * Import the subscriptions for the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\SubscriptionService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request, SubscriptionService $service)
    {
        $request->validate([
            'data.attributes.file' => 'required|file|mimes:xls,xlsx',
        ]);

        $user = $request->userOrClient();

        if ($user instanceof MerchantUser) {
            $merchant = $user->merchant;
        } else {
            $request->validate([
                'data.attributes.merchant_id' => [
                    'required',
                    Rule::exists('merchants', 'id')
                        ->where('is_enabled', true)
                        ->whereNotNull('verified_at')
                        ->withoutTrashed(),
                ],
            ]);

            $merchant = Merchant::find($request->input('data.attributes.merchant_id'));
        }

        $subscriptions = $service->import($merchant, $request->file('data.attributes.file'));

        DB::transaction(function() use ($subscriptions, $merchant) {
            $import = $merchant->subscriptionImports()->create();
            $subscriptions->each(function(Subscription $subscription) use ($import){
                $subscription->subscriptionImport()
                    ->associate($import)
                    ->save();

                $import->increment('subscription_count');
                $import->fill([
                        'total_amount' => $import->total_amount += $subscription->total_price
                    ])
                    ->saveQuietly();

            });
        });

        return new ResourceCollection($subscriptions);
    }


    /**
     * Parse the subscriptions for the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\SubscriptionService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function parse(Request $request, SubscriptionService $service)
    {
        $request->validate([
            'data.attributes.file' => 'required|file|mimes:xls,xlsx',
        ]);

        $user = $request->userOrClient();

        if ($user instanceof MerchantUser) {
            $merchant = $user->merchant;
        } else {
            $request->validate([
                'data.attributes.merchant_id' => [
                    'required',
                    Rule::exists('merchants', 'id')
                        ->where('is_enabled', true)
                        ->whereNotNull('verified_at')
                        ->withoutTrashed(),
                ],
            ]);

            $merchant = Merchant::find($request->input('data.attributes.merchant_id'));
        }

        $subscriptions = $service->parse($merchant, $request->file('data.attributes.file'));

        return new ResourceCollection($subscriptions);
    }

    /**
     * Cancel the given subscription.
     *
     * @param  \App\Http\Requests\SubscriptionRequest  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Services\SubscriptionService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(
        SubscriptionRequest $request, Subscription $subscription, SubscriptionService $service
    ) {
        try {
            $subscription = $service->cancel($subscription);
        } catch (Throwable $e) {
            throw new BadRequestException($e->getMessage());
        }

        return new Resource($subscription->fresh());
    }
}
