<?php

namespace App\Http\Controllers\Api\v2;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSubscriptionRequest;
use App\Http\Requests\ManageSubscriptionRequest;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\MerchantUser;
use App\Models\Subscription;
use App\Services\ProductService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        $this->middleware('auth:api,merchant,user');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $subscriptions = QueryBuilder::for(Subscription::class)
            ->when($user instanceof MerchantUser, function ($query) use ($user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($subscriptions);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateSubscriptionRequest  $request
     * @param  \App\Services\SubscriptionService  $subService
     * @param  \App\Services\ProductService  $productService
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(
        CreateSubscriptionRequest $request,
        SubscriptionService $subService,
        ProductService $productService
    ) {
        return DB::transaction(function () use ($request, $subService, $productService) {
            $bookingType = null;
            $products = $request->validated('products', []);

            if (! $ignoresInventory = $request->validated('ignores_inventory', false)) {
                $productService->checkStocks($request->merchant, $products);
            }

            if ($request->hasHeader('X-Api-Request')) {
                $bookingType = Subscription::BOOKING_API;
            } elseif ($request->user() instanceof MerchantUser) {
                $bookingType = Subscription::BOOKING_CONSOLE;
            }

            $subscription = $subService->create(
                $request->merchant,
                $request->safe()->except('customer', 'products'),
                $request->validated('customer'),
                $request->validated('products'),
                $bookingType,
                $request->validated('checkout_id'),
                $ignoresInventory
            );

            if (! $ignoresInventory) {
                $productService->takeStocks(
                    $request->merchant, $subscription->products()->get()->toArray()
                );
            }

            return new CreatedResource($subscription);
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ManageSubscriptionRequest  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Services\SubscriptionService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(
        ManageSubscriptionRequest $request,
        Subscription $subscription,
        SubscriptionService $service
    ) {
        return DB::transaction(function () use ($request, $subscription, $service) {
            $subscription->fill(Arr::except($request->validated(), 'other_info'));

            if ($otherInfo = $request->validated('other_info')) {
                $subscription->other_info = $service->formatMetaInfo(
                    $request->merchant, $otherInfo
                );
            }

            $subscription->save();

            return new Resource($subscription->fresh());
        });
    }

    /**
     * Cancel the given subscription.
     *
     * @param  \App\Http\Requests\ManageSubscriptionRequest  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Services\SubscriptionService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel(
        ManageSubscriptionRequest $request, Subscription $subscription, SubscriptionService $service
    ) {
        try {
            $subscription = $service->cancel($subscription);
        } catch (Throwable $e) {
            throw new BadRequestException($e->getMessage());
        }

        return new Resource($subscription->fresh());
    }
}
