<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCheckoutRequest;
use App\Http\Resources\CreatedResource;
use App\Services\ShippingMethodService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['logged', 'auth:api,user,merchant']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateCheckoutRequest  $request
     * @param  \App\Services\SubscriptionService  $subscriptionService
     * @param  \App\Services\ShippingMethodService  $shippingMethodService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(
        CreateCheckoutRequest $request,
        SubscriptionService $subscriptionService,
        ShippingMethodService $shippingMethodService
    ) {
        return DB::transaction(function () use ($request, $subscriptionService, $shippingMethodService) {
            $merchant = $request->merchant;

            $subscription = collect($request->safe()->except('other_info'));

            $otherInfo = $subscriptionService->formatMetaInfo(
                $merchant,
                $request->validated('other_info') ?? []
            );

            if ($otherInfo->isNotEmpty()) {
                $subscription->put('other_info', $otherInfo->toArray());
            }

            $province = $request->validated('shipping_province')
                ?? $request->validated('billing_province')
                ?? $request->validated('customer.province');

            $products = $subscriptionService
                ->formatProducts($merchant, $request->validated('products') ?? []);

            if (!is_null($province) && $products->contains('is_shippable', true)) {
                $shippingMethod = $shippingMethodService->guess($merchant, $province);

                $subscription->put('shipping_method_id', optional($shippingMethod)->getKey());
            }

            $checkout = $merchant->checkouts()
                ->make($request->safe([
                    'success_redirect_url',
                    'failure_redirect_url',
                    'max_payment_count',
                ]))
                ->forceFill([
                    'subscription' => $subscription->isNotEmpty()
                        ? ['attributes' => $subscription->toArray()]
                        : null,
                    'customer' => count($customer = $request->validated('customer') ?? [])
                        ? ['attributes' => $customer]
                        : null,
                    'products' => $subscriptionService
                        ->formatProducts($merchant, $request->validated('products') ?? [])
                        ->map(function ($product) {
                            return ['attributes' => $product];
                        })
                        ->toArray(),
                ]);

            $checkout->save();

            return new CreatedResource($checkout->fresh());
        });
    }
}
