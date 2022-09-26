<?php
namespace App\Http\Controllers\Api\v1;

use App\Facades\PayMaya;
use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Customer;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\Subscription;
use App\Services\PaymentService;
use App\Services\ProductService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class OrderController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant,customer')->only('index', 'show');
        $this->middleware('auth:user,merchant,customer,null')->only('update');
        $this->middleware('auth:user')->only('bulkUpdate');
        $this->middleware('permission:CP: Merchants - Edit|MC: Orders')->only('index','show');
        $this->middleware('permission:CP: Merchants - Edit')->only('destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $orders = QueryBuilder::for(Order::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('subscription', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($orders);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Order $order)
    {
        $merchant = QueryBuilder::for(Order::class)
            ->whereKey($order->getKey())
            ->apply()
            ->first();

        if (!$merchant) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return new Resource($order);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'data.*.id' => [
                'required',
                Rule::exists('orders', 'id')->withoutTrashed(),
            ],

            'data.*.attributes.billing_date' => 'date_format:Y-m-d',
        ]);

        return DB::transaction(function () use ($request) {
            $orders = collect($request->input('data'))
                ->map(function ($data) {
                    ($order = Order::find($data['id']))
                        ->update(Arr::only($data['attributes'] ?? [], 'billing_date'));

                    return $order->fresh();
                });

            return new ResourceCollection($orders);
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\ProductService  $productService
     * @param  int  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, ProductService $productService, $order)
    {
        $request->validate(['data' => 'required']);

        $order = Order::findOrFail($order);

        if (
            $request->hasOnly('order_status_id', 'data.attributes')
            || $request->hasOnly('shipped_at', 'data.attributes')
            || $request->hasOnly('fulfilled_at', 'data.attributes')
        ) {
            return $this->updateOrderStatus($request, $order);
        }

        if ($request->input('data.type') == 'consoleCheckout') {
            return $this->updateConsoleCreatedOrder($request, $productService, $order);
        }

        $merchant = $order->subscription->merchant()->first();
        $subscription = $order->subscription()->first();
        $nextOrders = $subscription->orders()
            ->whereNotIn('order_status_id', [
                OrderStatus::PAID,
                OrderStatus::SKIPPED,
                OrderStatus::CANCELLED
            ])
            ->whereKeyNot($order->id)
            ->get();

        $updateType = $request->input('data.type');
        $data = $request->input('data.attributes');

        if ($updateType == 'recurrence')    {
            return $this->convertToSubscription($subscription, $order, $data['products']);
        }

        if ($updateType === 'changePayment') {
            return $this->updatePayment(
                $request,
                $order,
                !$request->input('data.is_from_customer_profile')
            );
        }

        DB::transaction(function () use (
            $request,
            $order,
            $merchant,
            $subscription,
            $nextOrders,
            $updateType,
            $data
        ) {
            if ($updateType == 'customerAndShipping') {
                if ($request->filled('data.relationships.subscription.data.attributes')) {
                    $subscription->fill($request->input('data.relationships.subscription.data.attributes', []))->save();
                }
                $subscription->customer->fill($request->input('data.attributes', []))->save();
                $data = $request->input('data.relationships.shipping.data', []);
            }

            if ($data && $updateType != 'products') {
                $subscription->forceFill(
                    Arr::except($data, [
                        'shipped_at',
                        'fulfilled_at',
                        'shipping_date',
                        'fulfillment_date'
                    ])
                )->update();

                $nextOrders->each(function (Order $nextOrder) use($data) {
                    $nextOrder->fill(
                        Arr::except($data, [
                            'paymaya_payment_token_id',
                            'delivery_note',
                            'paymaya_wallet_customer_name',
                            'paymaya_wallet_mobile_number'
                        ])
                    )->save();
                });

                if (
                    $order->payment_status_id != PaymentStatus::PAID
                    && $updateType == 'changePayment'
                    || $updateType != 'changePayment'
                ) {
                    $order->forceFill(
                        Arr::except($data, [
                            'paymaya_payment_token_id',
                            'delivery_note',
                            'paymaya_wallet_customer_name',
                            'paymaya_wallet_mobile_number'
                        ])
                    )->update();
                }
            }

            if ($updateType == 'products') {
                $products = $request->input('data.attributes.products', []);

                if (
                    (!$order->total_price
                    && $merchant->pricing_type_id == 2)
                    || $merchant-> pricing_type_id == 1
                ) {
                    $subscription = $order->subscription;

                    $subscription->syncSubscribedProducts(
                        $subscription->mapProductData($products, $order),
                        $order
                    );
                }
            }

            if (in_array($updateType, ['products', 'customerAndShipping', 'changePayment'])) {
                $order->setTotalPrice();
                $subscription->setTotalPrice();

                $nextOrders->each(function (Order $nextOrder) {
                    $nextOrder->setTotalPrice();
                });
            }
        });

        try {
            $subscription = $subscription->fresh();
            $order = $order->fresh('products');

            if (
                ($updateType == 'pay'
                || $updateType == 'changePayment'
                && !$request->has('data.is_from_customer_profile'))
                && in_array((int) $order->payment_status_id, [
                    PaymentStatus::NOT_INITIALIZED,
                    PaymentStatus::PENDING,
                    PaymentStatus::FAILED,
                    PaymentStatus::INCOMPLETE
                ])
            ) {
                if (!$order->ignores_inventory) {
                    $productService->checkStocks($merchant, $order->products->toArray());
                }

                if ($this->isFree($order, $merchant)) {
                    $order
                        ->forceFill([
                            'payment_status_id' => PaymentStatus::PAID,
                            'payment_attempts' => ($order->payment_attempts ?: 0) + 1,
                        ])
                        ->update();

                    if (!$order->ignores_inventory) {
                        $productService->takeStocks($merchant, $order->products->toArray());
                    }

                    return new Resource($order->fresh('subscription'));
                }

                if ($this->isPaymentDisabled($order->payment_type_id, $merchant, $order)) {
                    return $this->getRedirection(
                        $order,
                        $subscription,
                        $merchant,
                        'payment_failed',
                        [ 'isPaymentMethodDisabled' => 1 ]
                    );
                }

                $this->initializePayment($subscription, $order);

                if (!$order->ignores_inventory) {
                    $productService->takeStocks($merchant, $order->products->toArray());
                }
            } else {
                $subscription->notifyCustomer('edit-confirmation', false, $order);
            }

            if (
                $order->payment_status_id == PaymentStatus::FAILED
                && $order->payment_type_id == PaymentType::PAYMAYA_WALLET
                && !$subscription->paymaya_link_id
                && !$subscription->paymaya_verification_url
            ) {
                return $this->getRedirection($order, $subscription, $merchant, 'payment_failed');
            }

        } catch (Throwable $e) {
            $status = $order->payment_status_id == PaymentStatus::PAID
                ? 'payment_success'
                : 'payment_failed';

            return $this->getRedirection(
                $order,
                $subscription,
                $merchant,
                $status
            );
        }

        return new Resource($order->fresh('subscription.products'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $merchant
     * @param  int  $customComponent
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Order $order)
    {
        if (!app()->environment('production')) {
            $order->attachments()->get()->each->delete();
            $order->attemptLogs()->get()->each->delete();
            $order->products()->get()->each->delete();
            $order->delete();

            return response()->json([], 204);
        }
    }

    /**
     * Update console created order
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\ProductService  $productService
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateConsoleCreatedOrder(
        Request $request,
        ProductService $productService,
        $order
    ) {
        $merchant = $order->subscription->merchant()->first();
        $subscription = $order->subscription;

        $user = $request->userOrClient();

        DB::transaction(function () use (
            $request,
            $order,
            $merchant,
            $subscription,
            $user
        ) {
            if ($user instanceof Customer) {
                $customer = $user;
            } elseif ($request->filled('data.relationships.customer.data.id')) {
                $customer = Customer::findOrFail(
                    $request->input('data.relationships.customer.data.id')
                );
            } else {
                $customer = $merchant->customers()->create(
                    $request->input('data.relationships.customer.data.attributes')
                );
            }

            if ($request->filled('data.relationships.customer.data.attributes.name')) {
                $customer->fill([
                    'name' => $request->input('data.relationships.customer.data.attributes.name'),
                ]);
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

            $subscription->forceFill($request->input('data.attributes'))->update();

            $subscription->orders()
                ->whereNotIn('order_status_id', [
                    OrderStatus::PAID,
                    OrderStatus::SKIPPED,
                    OrderStatus::CANCELLED
                ])
                ->whereKeyNot($order->id)
                ->get()
                ->each(function (Order $nextOrder) use($request) {
                    $nextOrder->forceFill(
                        Arr::except($request->input('data.attributes'), [
                            'paymaya_payment_token_id',
                            'paymaya_wallet_customer_name',
                            'paymaya_wallet_mobile_number',
                            'delivery_note',
                            'other_info',
                            'voucher_id'
                        ])
                    )->save();

                    $nextOrder->setTotalPrice();
                });

            $order->forceFill(
                Arr::except($request->input('data.attributes'), [
                    'paymaya_payment_token_id',
                    'paymaya_wallet_customer_name',
                    'paymaya_wallet_mobile_number',
                    'delivery_note',
                    'other_info',
                    'voucher_id'
                ])
            )->update();

            $order->setTotalPrice();
            $subscription->setTotalPrice();
        });

        try {
            $subscription = $subscription->fresh();
            $order = $order->fresh('products');

            if (
                in_array((int) $order->payment_status_id, [
                    PaymentStatus::NOT_INITIALIZED,
                    PaymentStatus::PENDING,
                    PaymentStatus::FAILED,
                    PaymentStatus::INCOMPLETE
                ])
            ) {
                if (!$order->ignores_inventory) {
                    $productService->checkStocks($merchant, $order->products->toArray());
                }

                if ($this->isFree($order, $merchant)) {
                    $order->setAttribute('payment_status_id', PaymentStatus::PAID)->update();

                    if (!$order->ignores_inventory) {
                        $productService->takeStocks($merchant, $order->products->toArray());
                    }

                    return new Resource($order->fresh('subscription'));
                }

                if ($this->isPaymentDisabled($order->payment_type_id, $merchant, $order)) {
                    return $this->getRedirection(
                        $order,
                        $subscription,
                        $merchant,
                        'payment_failed',
                        [ 'isPaymentMethodDisabled' => 1 ]
                    );
                }

                $this->initializePayment($subscription, $order);

                if (!$order->ignores_inventory) {
                    $productService->takeStocks($merchant, $order->products->toArray());
                }
            }

            if (
                $order->payment_status_id == PaymentStatus::FAILED
                && $order->payment_type_id == PaymentType::PAYMAYA_WALLET
            ) {
                return $this->getRedirection($order, $subscription, $merchant, 'payment_failed');
            }
        } catch (\Throwable $th) {
            $status = $order->payment_status_id == PaymentStatus::PAID
                ? 'payment_success'
                : 'payment_failed';

            return $this->getRedirection(
                $order,
                $subscription,
                $merchant,
                $status
            );
        }

        $resource = new Resource(
            $order->fresh(
                'subscription.customer',
                'subscription.products',
            )
        );

        if ($request->isFromGuest()) {
            return $resource->response()->withHeaders([
                'X-Bearer-Token' => $subscription->customer->createToken('Storefront Token')->accessToken,
            ]);
        } else {
            return $resource;
        }
    }


    /**
     * Check if the order is free.
     *
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Merchant  $merchant
     * @return bool
     */
    protected function isFree($order, $merchant)
    {
        return !$order->total_price
            || ($order->payment_type_id == PaymentType::CARD && $merchant->card_discount == 100);
    }

    /**
     * Initialize payment
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     * @return void
     */
    protected function initializePayment($subscription, $order)
    {
        if ($order->payment_type_id == PaymentType::PAYMAYA_WALLET) {
            (new PaymentService)->linkPayMayaWallet($order);
        }

        $order->setTotalPrice();
        $subscription->setTotalPrice();

        $order->startPayment();
    }

    /**
     * Check if payment is disabled
     *
     * @param int $paymentTypeId
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Order  $order
     *
     * @return bool
     */
    protected function isPaymentDisabled($paymentTypeId, $merchant, $order)
    {

        $paymentType = $merchant->paymentTypes()->where('payment_type_id', $paymentTypeId)->first();

        if (!$paymentType || !$paymentType->pivot->is_globally_enabled || !$paymentType->pivot->is_enabled) {
            return true;
        }

        if ($paymentTypeId == PaymentType::BANK_TRANSFER) {
            $paymentMethod = collect(json_decode($paymentType->pivot->payment_methods))->where('code', $order->bank->code)->first();

            if (!$paymentMethod || !$paymentMethod->is_globally_enabled || !$paymentMethod->is_enabled) {
                return true;
            }
        }

        return false;
    }

      /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Order  $order
     * @param array $data
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function convertToSubscription(Subscription $subscription, Order $order, $data)
    {
        return DB::transaction(function () use ($data, $subscription, $order) {
            $subscription->forceFill([
                'completed_at' => null,
                'cancelled_at' => null,
            ])->update();

            $subscription->syncSubscribedProducts(
                $subscription->mapProductData($data)
            );

            $oldestOrder = $subscription->orders()
                ->with('subscription')
                ->whereKeyNot($order->getKey())
                ->oldest('billing_date')
                ->first();

            $subscription->notifyCustomer('edit-confirmation',false, $oldestOrder);

            return new Resource(
                $oldestOrder
            );
        });
    }

    /**
     * Download Invoice Invoice
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadInvoice(Request $request, Order $order)
    {
        if (!optional($order)) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        $subscription = $order->subscription;
        $customer = $subscription->customer;

        return Pdf::loadView('pdf.invoice', [
            'customer' => $customer,
            'order' => $order,
            'merchant' => $subscription->merchant,
            'subscription' => $subscription
        ])->stream();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateOrderStatus(Request $request, Order $order)
    {
        return DB::transaction(function () use ($request, $order) {
            $order->update($request->input('data.attributes', []));

            return new Resource($order->fresh('subscription'));
        });
    }


    /**
     * Redirect to storefront
     *
     * @param  \App\Models\Order  $order
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\Merchant  $order
     * @param string $status
     * @param  array  $actions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function getRedirection(
        Order $order,
        Subscription $subscription,
        Merchant $merchant,
        $status,
        $actions = []
    ) {
        $url = config("bukopay.url.{$status}");

        $scheme = app()->isLocal() ? 'http' : 'https';
        $failedUrl = "{$scheme}://{$merchant->subdomain}.{$url}?" . http_build_query([
            'sub' => Hashids::connection('subscription')->encode($subscription->getKey()),
            'ord' => Hashids::connection('order')->encode($order->getKey()),
        ] + $actions);

        if (Arr::has($actions, 'isPaymentMethodDisabled')) {
            $order->forceFill([
                'payment_status_id' => PaymentStatus::FAILED,
                'payment_attempts' => ($order->payment_attempts ?: 0) + 1,
            ])->update();
        }

        return response()->json(['failed_url' => $failedUrl], 200);
    }

    /**
     * Update the payment for the order/subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @param  bool  $triggerPayment
     * @return \App\Models\Order
     */
    protected function updatePayment(Request $request, Order $order, $triggerPayment = false)
    {
        $subscription = $order->subscription;
        $merchant = $subscription->merchant;

        DB::transaction(function () use ($request, $order, $triggerPayment, $subscription) {
            if ($paymentTypeId = $request->input('data.attributes.payment_type_id')) {
                $subscription->update(['payment_type_id' => $paymentTypeId]);

                $order->setAttribute('payment_type_id', $paymentTypeId);
            }

            (new PaymentService)->resetPaymentInfo($order, $subscription);

            switch ((int) $order->payment_type_id) {
                case PaymentType::CARD:
                    (new PaymentService)->createPayMayaCustomer(
                        $subscription->customer,
                        $order
                    );

                    if ($request->filled('data.attributes.paymaya_payment_token_id')) {
                        (new PaymentService)->linkCardToCustomer(
                            $order,
                            $request->input('data.attributes.paymaya_payment_token_id'),
                            $request->input('data.attributes.paymaya_card_type'),
                            false
                        );
                    }

                    if ($request->filled([
                        'data.attributes.paymaya_card_token_id',
                        'data.attributes.paymaya_card_type',
                        'data.attributes.paymaya_masked_pan',
                    ])) {
                        $order->forceFill(
                            Arr::only($request->input('data.attributes'), [
                                'paymaya_card_token_id',
                                'paymaya_card_type',
                                'paymaya_masked_pan',
                            ])
                        );

                        $subscription->forceFill(
                            $order->only(
                                'paymaya_card_token_id',
                                'paymaya_card_type',
                                'paymaya_masked_pan',
                            )
                        );
                    }

                    break;

                case PaymentType::PAYMAYA_WALLET:
                    $subscription->paymaya_link_id = $request->input('data.attributes.paymaya_link_id');

                    if (!$triggerPayment) {
                        (new PaymentService)->linkPayMayaWallet($order, false);
                    }

                    break;

                case PaymentType::BANK_TRANSFER:
                    $subscription->bank_id = $request->input('data.attributes.bank_id');
                    $order->bank_id = $subscription->bank_id;

                    break;

                case PaymentType::GCASH:
                case PaymentType::GRABPAY:
                default:
                    // Do nothing
            }

            $order->setTotalPrice();
            $subscription->setTotalPrice();

            (new PaymentService)->cascadePaymentInfo($subscription, $order->getKey());
        });

        if ($triggerPayment) {
            DB::transaction(function () use ($order, $subscription, $merchant) {
                if (!$order->ignores_inventory) {
                    (new ProductService)->checkStocks($merchant, $order->products->toArray());
                }

                if ($this->isFree($order, $merchant)) {
                    $order->setAttribute('payment_status_id', PaymentStatus::PAID)->update();

                    if (!$order->ignores_inventory) {
                        (new ProductService)->takeStocks($merchant, $order->products->toArray());
                    }

                    return new Resource($order->fresh('subscription'));
                }

                if ($this->isPaymentDisabled($order->payment_type_id, $merchant, $order)) {
                    return $this->getRedirection(
                        $order,
                        $subscription,
                        $merchant,
                        'payment_failed',
                        ['isPaymentMethodDisabled' => 1]
                    );
                }

                $order->startPayment();

                if (!$order->ignores_inventory) {
                    (new ProductService)->takeStocks($merchant, $order->products->toArray());
                }
            });
        }

        $resource = new Resource($order->fresh('subscription.products', 'subscription.customer'));

        if ($request->isFromGuest()) {
            return $resource->response()->withHeaders([
                'X-Bearer-Token' => $order->subscription->customer
                    ->createToken('Storefront Token')
                    ->accessToken,
            ]);
        }

        return $resource;
    }
}
