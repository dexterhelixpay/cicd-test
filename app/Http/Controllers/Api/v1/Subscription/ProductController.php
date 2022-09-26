<?php

namespace App\Http\Controllers\Api\v1\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\SubscribedProduct;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;

class ProductController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:merchant');
        $this->middleware('permission:MC: Products');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Subscription $subscription)
    {
        $this->authorizeRequest($request, $subscription);

        $products = QueryBuilder::for($subscription->products()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($products);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Subscription $subscription)
    {
        $this->authorizeRequest($request, $subscription);
        $this->validateRequest($request, $subscription);

        return DB::transaction(function () use ($request, $subscription) {
            $subscription->syncSubscribedProducts($request->input('data', []));
            $subscription->setTotalPrice();

            return new ResourceCollection($subscription->products()->get());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\SubscribedProduct  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(
        Request $request,
        Subscription $subscription,
        SubscribedProduct $product
    ) {
        $this->authorizeRequest($request, $subscription);
        $this->validateRequest($request, $subscription, $product);

        return DB::transaction(function () use ($request, $subscription, $product) {
            $product->update(Arr::only($request->input('data.attributes'), [
                'title',
                'description',
                'images',
                'price',
                'quantity',
            ]));

            $product->setTotalPrice()->save();
            $subscription->setTotalPrice()->syncOrderedProducts();

            return new Resource($product->fresh());
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $subscription)
    {
        if ($request->isFromMerchant()) {
            if (!$subscription->merchant) {
                throw new UnauthorizedException;
            }

            $hasUser = $subscription->merchant->users()
                ->whereKey($request->userOrClient()->getKey())
                ->exists();

            if (!$hasUser) {
                throw new UnauthorizedException;
            }
        }
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\SubscribedProduct|null  $product
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $subscription, $product = null)
    {
        $request->validate([
            'data' => 'required',
        ]);

        if ($product) {
            return $request->validate([
                'data.attributes' => 'required',
                'data.attributes.title' => 'sometimes|string|max:255',
                'data.attributes.description' => 'sometimes|string|max:65535',
                'data.attributes.images' => 'sometimes|array',
                'data.attributes.images.*' => 'url',
                'data.attributes.price' => 'sometimes|nullable|numeric|min:0.01',
                'data.attributes.quantity' => 'sometimes|integer|min:1',
            ]);
        }

        $request->validate([
            'data.*.id' => [
                'sometimes',
                Rule::exists('subscribed_products', 'id')
                    ->where('subscription_id', $subscription->getKey()),
            ],
            'data.*.attributes.product_id' => [
                'sometimes',
                'nullable',
                Rule::exists('products', 'id')
                    ->where('merchant_id', $subscription->merchant_id)
                    ->withoutTrashed(),
            ],
            'data.*.attributes.title' => 'sometimes|string|max:255',
            'data.*.attributes.description' => 'sometimes|string|max:255',
            'data.*.attributes.images' => 'sometimes|array',
            'data.*.attributes.images.*' => 'url',
            'data.*.attributes.price' => 'sometimes|nullable|numeric|min:0.01',
            'data.*.attributes.quantity' => 'sometimes|integer|min:1',
        ]);
    }
}
