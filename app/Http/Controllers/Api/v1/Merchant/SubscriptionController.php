<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Order;
use App\Models\Country;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\OrderStatus;
use App\Models\PaymentType;
use Illuminate\Support\Arr;
use App\Models\MerchantUser;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\PaymentStatus;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use App\Support\PaymentSchedule;
use Illuminate\Support\Facades\DB;
use App\Imports\SubscriptionPrices;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;
use App\Http\Controllers\Controller;
use App\Notifications\PaymentReminder;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Imports\SubscriptionPricesImport;
use App\Http\Resources\ResourceCollection;
use App\Exceptions\MerchantAmountLimitException;
use Illuminate\Validation\UnauthorizedException;
use App\Notifications\NewSubscriberEmailNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Jobs\CreateShopifyOrder as CreateShopifyOrderJob;
use App\Exports\SubscriptionPrices as SubscriptionPricesExport;
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
        $this->middleware('lock:30,30')->only('store');
        $this->middleware('auth:user,merchant,customer')->only('index', 'destroy');
        $this->middleware('auth:user,merchant,customer,null')->only('store', 'show', 'update');
        $this->middleware('permission:CP: Merchants - Edit|MC: Subscriptions')->only('index', 'destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);
        $status = data_get($request, 'filter.status');

        $subscriptions = QueryBuilder::for($merchant->subscriptions()->getQuery())
            ->with('customer.country')
            ->when($status == 'active', function ($query) {
                $query->whereHas('orders', function ($query) {
                    $query->where('order_status_id', OrderStatus::PAID);
                })
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            })
            ->when($request->input('filter.is_last_order_was_paid'), function ($query) {
                $query->whereHas('orders', function ($query) {
                    $query->whereDate('paid_at', '<=', now()->toDateString())
                        ->where('payment_status_id', PaymentStatus::PAID)
                        ->where('orders.payment_schedule->frequency', '<>', 'single')
                        ->whereNotIn('order_status_id', [
                            OrderStatus::CANCELLED,
                            OrderStatus::FAILED,
                            OrderStatus::OVERDUE,
                            OrderStatus::INCOMPLETE
                        ]);
                })
                ->whereDoesntHave('orders', function ($query) {
                    $query->where('billing_date', '<=', now()->toDateString())
                        ->whereIn('payment_status_id', [
                            PaymentStatus::NOT_INITIALIZED,
                            PaymentStatus::PENDING,
                            PaymentStatus::INCOMPLETE
                        ]);
                });
            })
            ->when($status == 'pending', function ($query) {
                $query->whereHas('orders', function ($query) {
                    $query->whereIn('order_status_id', [OrderStatus::UNPAID, OrderStatus::FAILED, OrderStatus::INCOMPLETE]);
                })
                    ->whereNull('completed_at')
                    ->whereNull('cancelled_at');
            })
            ->when($status == 'inactive', function ($query) {
                $query->where(function ($query) {
                    $query->whereNotNull('completed_at')
                        ->orWhereNotNull('cancelled_at');
                });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($subscriptions);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Services\ProductService  $productService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant, ProductService $productService)
    {
        if ($request->hasFile('data.subscription_prices')) {
            return $this->importSubscriptionPrices($request);
        }

        if ($request->hasFile('data.attributes.price_file')) {
            return $this->updateSubscriptionPrices($request, $merchant);
        }

        $this->authorizeRequest($request, $merchant);
        $this->validateRequest($request, $merchant);

        if (!$ignoresInventory = $request->input('data.attributes.ignores_inventory', false)) {
            $productService->checkStocks(
                $merchant,
                $request->input('data.relationships.products.data', [])
            );
        }

        return DB::transaction(function () use ($request, $merchant, $productService, $ignoresInventory) {
            $subscription = $merchant->subscriptions()->make($request->input('data.attributes'));

            if ($request->has('data.attributes.paymaya_link_id')) {
                $isVaulted = $subscription->isWalletVerified();

                if (!$isVaulted) {
                    $subscription->paymaya_link_id = null;
                }
            }

            $subscription->is_console_booking = (bool) $request->isFromMerchant();
            $customerDetails = $request->input('data.relationships.customer.data.attributes');

            if ($request->isFromCustomer()) {
                $customer = $request->userOrClient();
            } elseif ($request->filled('data.relationships.customer.data.id')) {
                $customer = Customer::findOrFail(
                    $request->input('data.relationships.customer.data.id')
                );
            } else {
                $customer = $merchant->customers()->updateOrcreate(
                    ['mobile_number' => data_get($customerDetails, 'mobile_number')],
                    Arr::except($customerDetails,'mobile_number')
                );
            }

            $customerName = data_get($customerDetails, 'name');
            $customerCountryName = data_get($customerDetails, 'country_name');
            $customerProvince = data_get($customerDetails, 'province');
            $customerAddress = data_get($customerDetails, 'address');
            $customerEmail = data_get($customerDetails, 'email');
            $customerMobile = data_get($customerDetails, 'mobile_number');

            if (
                $customer->name !== $customerName
                || $customer->country_name !== $customerCountryName
                || $customer->province !== $customerProvince
                || $customer->address !== $customerAddress
                || $customer->email !== $customerEmail
                || $customer->mobile_number !== $customerMobile
            ) {
                $customer->update(Arr::only($customerDetails, [
                    'name',
                    'email',
                    'address',
                    'barangay',
                    'city',
                    'country_name',
                    'province',
                    'zip_code',
                    'mobile_number'
                ]));
            }

            $subscription->customer()->associate($customer);

            if ($request->isNotFilled('data.attributes.payor')) {
                $subscription->fill([
                    'payor' => $customer->name,
                    'billing_address' => $customer->address,
                    'billing_province' => $customer->province,
                    'billing_country' => $customer->country_name,
                    'billing_city' => $customer->city,
                    'billing_barangay' => $customer->barangay,
                    'billing_country' => $customer->country_name,
                    'billing_zip_code' => $customer->zip_code,
                ]);
            }

            $hasShippableProducts = are_shippable_products(collect($request->input('data.relationships.products.data'))
                ->pluck('attributes')
                ->filter(function ($product) {
                    return $product['quantity'] ?? 0;
                }));

            if (
                ($merchant->has_shippable_products || $hasShippableProducts)
                && $request->isNotFilled('data.attributes.recipient')
            ) {
                $subscription->fill([
                    'recipient' => $customer->name,
                    'shipping_address' => $customer->address,
                    'shipping_province' => $customer->province,
                    'shipping_country' => $customer->country_name,
                    'shipping_city' => $customer->city,
                    'shipping_barangay' => $customer->barangay,
                    'shipping_country' => $customer->country_name,
                    'shipping_zip_code' => $customer->zip_code,
                ]);
            }

            if ($request->filled('data.attributes.max_payment_count')) {
                $subscription->max_payment_count = $request->input('data.attributes.max_payment_count');
            }

            if ($checkoutId = $request->input('data.attributes.checkout_id')) {
                [$checkoutId] = Hashids::connection('checkout')->decode($checkoutId);
                $checkout = Checkout::findOrFail($checkoutId);

                if ($otherInfo = data_get($checkout, 'subscription.attributes.other_info')) {
                    $otherInfo = collect($otherInfo)
                        ->merge($subscription->other_info ?? [])
                        ->map(function ($info) use ($merchant) {
                            if (!$field = $merchant->subscriptionCustomFields->firstWhere('code', $info['code'])) {
                                return null;
                            }

                            return Arr::except($field->toArray(), ['created_at', 'updated_at'])
                                + Arr::only($info, 'value');
                        })
                        ->filter();

                    $subscription->other_info = $otherInfo->isEmpty() ? null : $otherInfo->toArray();
                }

                if ($referenceId = data_get($checkout, 'subscription.attributes.reference_id')) {
                    $subscription->reference_id = $referenceId;
                }

                $subscription
                    ->forceFill($checkout->only([
                        'max_payment_count',
                        'success_redirect_url',
                        'failure_redirect_url',
                    ]))
                    ->setAttribute('is_checkout_booking', true);

                $checkout->delete();
            }

            if (
                $request->isFromCustomer()
                && $request->filled('data.attributes.customer_card_id')
                && ($card = $customer->cards()->find($request->input('data.attributes.customer_card_id')))
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
                    'paymaya_masked_pan' => $request->input('data.attributes.paymaya_masked_pan')
                ]);
            }

            $subscription->save();

            $subscription->products()->saveMany(
                collect($request->input('data.relationships.products.data'))
                    ->pluck('attributes')
                    ->filter(function ($product) {
                        return $product['quantity'] ?? 0;
                    })
                    ->map(function ($product) use ($subscription) {
                        $variant = ProductVariant::with('optionValues')
                            ->whereKey(data_get($product, 'product_variant_id'))
                            ->first();

                        if (!$variant) {
                            $recurrenceValue = data_get($product, 'payment_schedule.frequency');

                            $variant = ProductVariant::with('optionValues')
                                ->where('product_id', data_get($product, 'product_id'))
                                ->where(function ($query) use ($recurrenceValue) {
                                    $query
                                        ->whereHas('optionValues', function ($query) use ($recurrenceValue) {
                                            $query
                                                ->where('value', $recurrenceValue)
                                                ->whereHas('option', function ($query) {
                                                    $query->where('code', 'recurrence');
                                                });
                                        })
                                        ->orWhere('is_default', true);
                                })
                                ->orderBy('is_default')
                                ->first();
                        }

                        if (Arr::has($product, 'price')) {
                            $price = data_get($product, 'price') ?: 0;

                            $product['total_price'] = $price
                                ? $price * data_get($product, 'quantity', 0)
                                : null;
                        } elseif ($variant) {
                            $price = $variant->price ?: 0;

                            $product = array_merge($product, [
                                'price' => $price ?: null,
                                'total_price' => $price
                                    ? $price * data_get($product, 'quantity', 0)
                                    : null,
                            ]);
                        }

                        $subscribedProduct = $subscription->products()
                            ->make(array_merge($product, [
                                'product_variant_id' => optional($variant)->getKey(),
                                'option_values' => optional($variant)->mapOptionValues(),
                            ]));

                        if ($variant && $variant->initially_discounted_order_count) {
                            $price = $variant->price ?: 0;
                            $discountedPrice = $variant->initially_discounted_price ?: 0;

                            if (
                                $subscription->is_console_booking &&
                                data_get($product, 'initially_discounted_price') !== data_get($product, 'price') &&
                                data_get($product, 'price') !== $price
                            ) {
                                $price = data_get($product, 'price', $price);
                                $discountedPrice = data_get($product, 'initially_discounted_price', $discountedPrice);
                            }

                            $subscribedProduct->forceFill([
                                'price' => $price ?: null,
                                'total_price' => $price
                                    ? $price * data_get($subscribedProduct, 'quantity', 0)
                                    : null,
                                'max_discounted_order_count' => $variant->initially_discounted_order_count,
                                'discounted_price' => $discountedPrice ?: null,
                                'total_discounted_price' => $discountedPrice
                                    ? $discountedPrice * data_get($subscribedProduct, 'quantity', 0)
                                    : null,
                            ]);
                        }

                        return $subscribedProduct;
                    })
            );

            if (!$ignoresInventory) {
                $productService->takeStocks(
                    $merchant, $subscription->products()->get()->toArray()
                );
            }

            $subscription
                ->setTotalPrice()
                ->createInitialOrders();

            $isCardVaulted = $subscription->isCardVaulted();

            if ($request->isFromGuest() || $request->isFromCustomer()) {
                $subscription->initializePayment();
            }

            if ($request->hasFile('data.relationships.attachments.data.*.attributes.pdf')) {
                collect($request->file('data.relationships.attachments.data'))
                    ->pluck('attributes.pdf')
                    ->each(function ($pdf) use ($subscription) {
                        $attachment = $subscription->attachments()->make();
                        $attachment->size = $pdf->getSize();
                        $attachment->name = $pdf->getClientOriginalName();

                        $attachment->uploadAttachment($pdf)->save();

                        $subscription->orders()
                            ->first()
                            ->attachments()
                            ->attach($attachment->getKey());
                    });
            }

            $order = $subscription->initialOrder()->first();

            if (
                !$order->total_price
                && ($order->payment_type_id != PaymentType::CARD
                    || ($order->payment_type_id == PaymentType::CARD
                        && $isCardVaulted))
            ) {
                $order->forceFill([
                    'payment_status_id' => PaymentStatus::PAID,
                ])->update();
            } elseif ($request->isFromMerchant()) {
                if ($subscription->created_at->isSameDay($order->billing_date)) {
                    $subscription->notifyCustomer(
                        'payment',
                        in_array($merchant->getKey(), setting('CustomMerchants', []))
                    );
                } else {
                    $this->sendPaymentReminder($subscription, $order);
                }
            }

            $resource = new CreatedResource(
                $subscription->fresh(
                    'customer',
                    'initialOrder',
                    'products',
                    'attachments',
                    'products.selectedVariant'
                )
            );

            if ($request->isFromGuest()) {
                return $resource->response()->withHeaders([
                    'X-Bearer-Token' => $customer->createToken('Storefront Token')->accessToken,
                ]);
            } else {
                return $resource;
            }
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $subscription)
    {
        $subscription = QueryBuilder::for($merchant->subscriptions()->getQuery())
            ->whereKey($subscription)
            ->apply()
            ->first();

        if (!$subscription) {
            throw (new ModelNotFoundException)->setModel(Subscription::class);
        }

        return new Resource($subscription);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $subscription)
    {
        $this->authorizeRequest($request, $merchant);

        $this->validateRequest(
            $request,
            $merchant,
            $subscription = $merchant->subscriptions()->findOrFail($subscription)
        );

        if ($request->has('data.action')) {
            return $this->generateOrder($request, $subscription);
        }

        return DB::transaction(function () use ($request, $subscription) {
            $subscription->update($request->input('data.attributes', []));

            $subscription->setTotalPrice();

            $subscription->orders()
                ->whereNotIn('order_status_id', [
                    OrderStatus::PAID,
                    OrderStatus::SKIPPED,
                    OrderStatus::CANCELLED
                ])
                ->get()
                ->each(function (Order $nextOrder) use($request) {
                    $nextOrder->fill(
                        $request->input('data.attributes')
                    )->save();

                    $nextOrder->setTotalPrice();
                });


            if ($request->hasOnly(['voucher_id', 'order_id'], 'data.attributes')) {
                if ($subscription->voucher) {
                    $order = $subscription->orders()->where('id', $request->input('data.attributes.order_id'))->first();

                    $voucher = $subscription->voucher;
                    $voucher->use($order);
                    $order->setTotalPrice();
                }
            }

            if ($request->filled('data.relationships.order.data')) {
                $order = $subscription->orders()
                    ->findOrFail(data_get($request->input('data.relationships.order.data'), 'id'));
                $order->update($request->input('data.relationships.order.data.attributes'));
                $order->setTotalPrice();
            }

            if ($request->filled('data.relationships.products.data')) {
                $products = $request->input('data.relationships.products.data', []);

                $subscription->syncSubscribedProducts(
                    $subscription->mapProductData($products)
                );
            }

            if ($request->allFiles()) {
                $subscription->saveShopifyImages($request->allFiles());
            }

            if (
                !($request->hasOnly('order_status_id', 'data.attributes')
                    || $request->hasOnly(['order_status_id', 'payment_type_id'], 'data.attributes')
                    || $request->hasOnly('cancelled_at', 'data.attributes')
                )
                && !$request->isFromMerchant()
            ) {
                $subscription->notifyCustomer('edit-confirmation');
            }

            if ($subscription->isCompleted() && !$subscription->hasPaidAllOrders()) {
                $subscription->forceFill([
                    'completed_at' => null,
                    'cancelled_at' => null,
                ])->saveQuietly();
            }

            return new Resource($subscription->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $subscription)
    {
        $subscription = Subscription::find($subscription);

        if (!optional($subscription)->delete()) {
            throw (new ModelNotFoundException)->setModel(Subscription::class);
        }

        return response()->json([], 204);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateMembership(Request $request)
    {
        $request->validate([
            'data.attributes.customer_id'  => 'required',
            'data.attributes.product_group_id'  => 'required'
        ]);

        $hasPaidMembership = Subscription::query()
            ->where('customer_id', $request->input('data.attributes.customer_id'))
            ->where('is_membership', true)
            ->with('orders')
            ->whereHas('products.product.groups', function ($query) use ($request) {
                $query->where(
                    'merchant_product_group_id',
                    $request->input('data.attributes.product_group_id')
                );
            })
            ->get()
            ->contains(function (Subscription $subscription) use ($request) {
                $lastPaidOrder = $subscription
                    ->recentOrders()
                    ->whereHas('products.product.groups', function ($query) use ($request) {
                        $query->where(
                            'merchant_product_group_id',
                            $request->input('data.attributes.product_group_id')
                        );
                    })
                    ->where('order_status_id', OrderStatus::PAID)
                    ->get()
                    ->last();

                if (!$lastPaidOrder) return false;

                if (!$lastPaidOrder->payment_schedule && $lastPaidOrder->isInitial()) {

                    $nextOrder = $lastPaidOrder->subscription
                        ->orders()
                        ->whereHas(
                            'products.product.groups',
                            function ($query) use ($request) {
                                $query->whereIn('id', [$request->input('data.attributes.product_group_id')]);
                            }
                        )
                        ->get()
                        ->last();

                    return Carbon::parse($nextOrder->billing_date)->gte(now()->toDateString());
                }

                $nextBillingDate = PaymentSchedule::getNextEstimatedBillingDate(
                    $lastPaidOrder->payment_schedule,
                    $lastPaidOrder->billing_date
                );

                if (!$nextBillingDate) return false;

                return $nextBillingDate->gte(now()->toDateString());
            });

        return response()->json([
            'hasPaidMembership' => $hasPaidMembership,
            'product_group_id' => $request->input('data.attributes.product_group_id')
        ]);
    }

    /**
     * Import order prices from the specified file.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function importSubscriptionPrices(Request $request)
    {
        $request->validate([
            'data.subscription_prices'  => 'required|mimes:csv,xlsx'
        ]);

        (new SubscriptionPrices)->import($request->file('data.subscription_prices'));

        return $this->okResponse();
    }

    /**
     * Send new subscriber email notification to merchants.
     *
     * @param  \App\Models\Subscription  $subscription
     * @return void
     */
    public function sendNewSubscriberNotification($subscription)
    {
        $merchant = $subscription->merchant;

        $merchant->users()
            ->get()
            ->each(function (MerchantUser $user) use ($subscription, $merchant) {
                if (
                    !$user->email
                    || !$merchant->is_new_subscriber_email_enabled
                    || !$user->is_enabled
                    || !$user->email_verified_at
                ) return;

                $user->notify((new NewSubscriberEmailNotification($merchant, $subscription)));
            });


        $products = formatProducts($subscription->products()->get());

        $merchant->sendViberNotification(
            "You have a new subscriber {$subscription->customer->name} for {$products}."
        );
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
            || $request->isFromCustomer()
            && $merchant->customers()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Authorize the request.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     *
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function sendPaymentReminder(Subscription $subscription, Order $order)
    {
        $merchant = $subscription->merchant;
        $customer = $subscription->customer;
        $orderProduct  = $order->products->first();

        $month = Carbon::parse($order->billing_date)->format('F');
        $day = ordinal_number(Carbon::parse($order->billing_date)->format('j'));

        $billingDate = "{$month} {$day}";

        if ($customer->email) {
            $title = $merchant->console_created_email_headline_text ?? "Start your {$merchant->subscription_term_singular} with {$merchant->name}.";

            $subheader = $merchant->console_created_email_subheader_text ?? "Please enter your payment details to activate your {$merchant->subscription_term_singular}.";

            $subtitle = count($order->attachments) > 0
                ? "{$subheader}. See attached file for full invoice."
                : "{$subheader}";

            $options = [
                'title' => $title,
                'type' => 'today',
                'subtitle' => $subtitle,
                'payment_headline' => replace_placeholders('Payment is due on {billingDate}', $order),
                'payment_instructions' => replace_placeholders('Please pay on or before {billingDate}', $order),
                'payment_button_label' => pay_button_text($subscription, $order->id),
                'total_amount_label' => 'Total Amount',
                'payment_instructions_headline' => replace_placeholders('Payment is due on {billingDate}', $order),
                'payment_instructions_subheader' => replace_placeholders('Please pay on or before {billingDate}', $order),
                'next_payment_subtitle' => "Your payment is due on {$billingDate}",
                'subject' => "Payment Due - {$billingDate}",
                'has_order_summary' => $order->isInitial()
                    && in_array($order->order_status_id, [
                        OrderStatus::UNPAID,
                        OrderStatus::FAILED,
                    ]),
                'has_pay_button' => true,
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
     * Update the given subscription' prices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateSubscriptionPrices(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        $request->validate([
            'data.attributes.price_file' => 'required|mimes:xlsx',
        ]);

        $subscriptionPrices = tap(new SubscriptionPricesImport($merchant))
            ->import($request->file('data.attributes.price_file'));

        return new ResourceCollection($subscriptionPrices->getSubscriptions());
    }


    /**
     * Export the given orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     */
    public function export(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (filter_var($request->input('filter.template'), FILTER_VALIDATE_BOOL)) {
            $query = Subscription::whereNull('id');
            $fileName = 'Subscriptions Template.xlsx';
        } else {
            $query = QueryBuilder::for($merchant->subscriptions()->getQuery())
                ->apply()
                ->with('products.product');
            $fileName = "{$merchant->name} Subscriptions (" . now()->format('YmdHis') . ').xlsx';
        }

        return (new SubscriptionPricesExport($query))->download($fileName);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Subscription|null  $subscription
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $subscription = null)
    {
        if ($subscription) {
            if ($request->has('data.attributes.max_payment_count')) {
                $paidOrdersCount = $subscription
                    ->orders()
                    ->where('order_status_id', OrderStatus::PAID)
                    ->count() + 1;

                $request->validate([
                    'data.attributes.max_payment_count' => [
                        'nullable',
                        'numeric',
                        'min:' . $paidOrdersCount
                    ]
                ], [
                    'min' => "Max Subscription Limit must be at least $paidOrdersCount",
                ]);
            }

            return;
        }


        $hasShippableProducts = are_shippable_products(collect($request->input('data.relationships.products.data'))
            ->pluck('attributes')
            ->filter(function ($product) {
                return $product['quantity'] ?? 0;
            }));


        $philippines = Country::where('name', 'Philippines')->first();

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.payment_type_id' => [
                'nullable',
                Rule::exists('payment_types', 'id')
            ],

            'data.attributes.paymaya_payment_token_id' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->isFromGuest()
                        && $request->input('data.attributes.payment_type_id') == PaymentType::CARD;
                }),
            ],
            'data.attributes.customer_card_id' => Rule::when($request->isFromCustomer(), [
                'sometimes',
                Rule::exists('customer_cards', 'id')
                    ->where('customer_id', optional($request->userOrClient())->getKey())
            ]),
            'data.attributes.paymaya_card_type' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->isFromGuest()
                        && $request->input('data.attributes.payment_type_id') == PaymentType::CARD
                        && !$request->input('data.attributes.paymaya_payment_token_id');
                }),
                'nullable',
                Rule::in(['master-card', 'visa', 'jcb']),
            ],

            'data.attributes.voucher_id' => [
                'sometimes',
                'nullable',
                Rule::exists('vouchers', 'id')->whereNull('deleted_at'),
            ],

            'data.attributes.total_price' => 'nullable|numeric|min:0',

            'data.attributes.payor' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_address' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_province' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_city' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_barangay' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_country' => 'sometimes|nullable|string|max:191',
            'data.attributes.billing_zip_code' => 'sometimes|nullable|string|max:5',

            'data.attributes.shipping_method_id' => [
                'sometimes',
                Rule::exists('shipping_methods', 'id')
                    ->where('merchant_id', $merchant->getKey()),
            ],

            'data.attributes.recipient' => 'sometimes|nullable|string|max:191',
            'data.attributes.shipping_address' => [
                Rule::requiredIf(
                    $merchant->is_address_enabled
                    && !$request->isFromMerchant()
                    && $hasShippableProducts
                ),
                'nullable',
                'string',
                'max:191'
            ],
            'data.attributes.shipping_province' => [
                Rule::requiredIf(
                    $merchant->is_address_enabled
                    && !$request->isFromMerchant()
                    && $hasShippableProducts
                ),
                'nullable',
                'string',
                'max:191'
            ],
            'data.attributes.shipping_city' => [
                Rule::requiredIf(
                    $merchant->is_address_enabled
                    && !$request->isFromMerchant()
                    && $hasShippableProducts
                ),
                'nullable',
                'string',
                'max:191'
            ],
            'data.attributes.shipping_barangay' => [
                Rule::requiredIf(
                    $merchant->is_address_enabled
                    && !$request->isFromMerchant()
                    && $hasShippableProducts
                    && $request->input('data.attributes.country_name') == 'Philippines'
                ),
                'nullable',
                'string',
                'max:191'
            ],
            'data.attributes.shipping_country' => [
                'nullable',
                'string',
                'max:191'
            ],
            'data.attributes.shipping_zip_code' => [
                Rule::requiredIf(
                    $merchant->is_address_enabled
                    && !$request->isFromMerchant()
                    && $hasShippableProducts
                ),
                'nullable',
                'string',
                'max:5'
            ],

            'data.attributes.max_payment_count' => 'sometimes|nullable|integer|min:1',
            'data.attributes.checkout_id' => 'sometimes|nullable|checkout_hash',

            'data.relationships.customer.data.attributes.name' => 'required|string|max:191',
            'data.relationships.customer.data.attributes.email' => [
                Rule::requiredIf(!$request->input('data.relationships.customer.data.attributes.mobile_number')),
                'sometimes',
                'nullable',
                'email',
                'max:191',
            ],
            'data.relationships.customer.data.attributes.mobile_number' => [
                Rule::requiredIf(!$request->input('data.relationships.customer.data.attributes.email')),
                'sometimes',
                'nullable',
                Rule::when(
                    $request->input('data.relationships.customer.data.attributes.country_name') == 'Philippines',
                    'mobile_number',
                    'numeric'
                ),
            ],
            'data.relationships.customer.data.attributes.address' => [
                Rule::requiredIf($merchant->is_address_enabled),
                'nullable',
                'string',
                'max:191'
            ],
            'data.relationships.customer.data.attributes.province' => [
                Rule::requiredIf(
                    $request->input('data.attributes.country_name') == 'Philippines'
                    && $merchant->is_address_enabled
                ),
                'nullable',
                'string',
                'max:191',
            ],
            'data.relationships.customer.data.attributes.barangay' => [
                Rule::requiredIf(
                    $request->input('data.relationships.customer.data.attributes.country_name') == 'Philippines'
                    && $merchant->is_address_enabled
                ),
                'nullable',
                'string',
                'max:191',
            ],
            'data.relationships.customer.data.attributes.city' => [
                Rule::requiredIf($merchant->is_address_enabled),
                'nullable',
                'string',
                'max:191'
            ],
            'data.relationships.customer.data.attributes.zip_code' => [
                Rule::requiredIf($merchant->is_address_enabled),
                'nullable',
                'string',
                'max:5'
            ],

            'data.relationships.customer.data.attributes.country_name' => [
                Rule::requiredIf(!$merchant->is_address_enabled),
                'nullable',
                'string',
            ],

            'data.relationships.products.data' => 'required',
            'data.relationships.products.data.*.attributes.product_id' => [
                'sometimes',
                'nullable',
            ],
            'data.relationships.products.data.*.attributes.title' => 'required|string|max:191',
            'data.relationships.products.data.*.attributes.description' => 'nullable|string',
            'data.relationships.products.data.*.attributes.payment_schedule' => 'required',
            'data.relationships.products.data.*.attributes.payment_schedule.frequency' => [
                'required',
                Rule::in('interval', 'weekly', 'semimonthly', 'monthly', 'single', 'quarterly', 'annually', 'semiannual', 'bimonthly'),
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
            'data.relationships.products.data.*.attributes.payment_schedule.month' => [
                'integer',
                'min:1',
                'max:12',
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
            'data.relationships.products.data.*.attributes.total_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],
            'data.relationships.products.data.*.attributes.are_multiple_orders_allowed' => [
                'required',
                'boolean',
            ],
            'data.relationships.attachments.data' => 'nullable|array|email_attachment',
            'data.relationships.attachments.data.*.attributes.pdf' => [
                'mimes:pdf',
                'max:2048',
            ],
        ], [
            'data.relationships.attachments.data.*.attributes.pdf.mimes' => 'Attachments must be only PDF files.',
            'data.relationships.attachments.data.*.attributes.pdf.max' => 'Attachments must not be greater than 2 MB.',
        ]);

        $shippingFee = $request->filled('data.attributes.shipping_method_id')
            ? ShippingMethod::whereKey($request->input('data.attributes.shipping_method_id'))->value('price')
            : 0;

        $products = collect($request->input('data.relationships.products.data'))
            ->pluck('attributes')
            ->map(function ($product) {
                $product['total_price'] = data_get($product, 'price', 0)
                    ? data_get($product, 'price', 0) * data_get($product, 'quantity', 0)
                    : null;

                return $product;
            });

        if (!are_shippable_products($products->pluck('attributes'))) {
            $shippingFee = 0;
        }

        // $merchantTotalAmountPaid = Subscription::calculateTotalPrice(
        //     $merchant,
        //     $products,
        //     $request->input('data.attributes.payment_type_id'),
        //     $shippingFee
        // ) + $merchant->hourly_total_amount_paid;

        // $maxLimit = $merchant->max_payment_limit ?? setting('MerchantMaxAmountLimit', 250000);

        // if ($merchantTotalAmountPaid >= $maxLimit || $merchant->has_reached_max_amount) {
        //     $merchant->has_reached_max_amount = true;
        //     $merchant->update();

        //     throw new MerchantAmountLimitException;
        // }
    }

    /**
     * Apply voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Subscription  $subscription
     *
     * @return \Illuminate\Http\JsonResponse
     *
     */
    protected function applyVoucher(Request $request, Merchant $merchant, Subscription $subscription)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.voucher_code' => [
                'required',
                Rule::exists('vouchers', 'code')
                    ->where('merchant_id', $merchant->getKey())
                    ->whereNull('deleted_at')
            ]
        ], [
            'data.attributes.voucher_code.exists' => 'This is not an active voucher code',
        ]);

        return DB::transaction(function () use ($request, $merchant, $subscription) {
            $voucher = Voucher::validate(
                code: $request->input('data.attributes.voucher_code'),
                totalPrice: $subscription->total_price ?? 0,
                merchantId: $merchant->id,
                customerId: $subscription->customer->id,
                products: $subscription->products()->get()
            );

            $orders = $subscription->orders()
                ->whereNull('voucher_code')
                ->where('order_status_id', OrderStatus::UNPAID)
                ->when($request->filled('data.attributes.order_id'), function ($query) use ($request) {
                    $query->where('id', $request->input('data.attributes.order_id'));
                })
                ->orderBy('billing_date')
                ->get();

            $orders->each(function (Order $order) use ($voucher, $subscription) {
                $usedVoucherCount = $subscription->customer
                    ->usedVouchers()
                    ->where('id', $voucher->id)
                    ->count();

                if (
                    $voucher->remaining_count
                    && $order->total_price
                    && $usedVoucherCount < ($voucher->applicable_order_count ?? PHP_INT_MAX)
                ) {
                    $voucher->use($order);
                    $order->setTotalPrice();

                    $subscription->voucher_id = $voucher->id;
                    $subscription->saveQuietly();

                    if (!$order->total_price) {
                        $order = $order->fresh();
                        $order->forceFill(['payment_status_id' => PaymentStatus::PAID])->update();
                    }
                }
            });

            return new Resource($subscription->fresh());
        });
    }

    /**
     * Apply voucher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Subscription  $subscription
     *
     * @return \Illuminate\Http\JsonResponse
     *
     */
    protected function removeVoucher(Request $request, Merchant $merchant, Subscription $subscription)
    {
        return DB::transaction(function () use ($request, $merchant, $subscription) {
            $orders = $subscription->orders()
                ->where('voucher_code', $subscription->voucher->code)
                ->where('order_status_id', OrderStatus::UNPAID)
                ->when($request->filled('data.attributes.order_id'), function ($query) use ($request) {
                    $query->where('id', $request->input('data.attributes.order_id'));
                })
                ->get();

            $orders->each(function (Order $order) use ($subscription) {

                $order->forceFill(['voucher_code' => null])->update();
                $order->voucher()->sync([]);
                $order->setTotalPrice();

                $subscription->voucher->increment('remaining_count');
                $subscription->fill(['voucher_id' => null])->saveQuietly();
            });

            return new Resource($subscription->fresh());
        });
    }

    /**
     * Generate order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     *
     * @return \Illuminate\Http\JsonResponse
     *
     */
    protected function generateOrder(Request $request, Subscription $subscription)
    {
        return DB::transaction(function () use ($request, $subscription) {
            $type = $request->input('data.attributes.type');
            $lastOrder = $subscription->orders()
                ->where('group_number', data_get($request,'data.attributes.group_number'))
                ->when($type == 'shopify', function($query) {
                    $query->where('order_status_id', OrderStatus::PAID);
                })
                ->get()
                ->last();

            if (!$lastOrder) return;

            if ($type == 'non-shopify') {
                $subscription->generateNextOrders(
                    data_get($request,'data.attributes.group_number'),
                    'create',
                    $lastOrder
                );
            }

            if ($type == 'shopify') {
                dispatch(new CreateShopifyOrderJob($lastOrder, true));
            }

            return new Resource($subscription);
        });
    }


}
