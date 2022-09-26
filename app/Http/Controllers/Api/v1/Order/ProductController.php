<?php

namespace App\Http\Controllers\Api\v1\Order;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\OrderedProduct;
use App\Models\Order;
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
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Order $order)
    {
        $this->authorizeRequest($request, $order);

        $products = QueryBuilder::for($order->products()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($products);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Order $order)
    {
        $this->authorizeRequest($request, $order);
        $this->validateRequest($request, $order);

        return DB::transaction(function () use ($request, $order) {
            $products = collect($request->input('data', []));
            $productKeys = $products->pluck('id');

            $order->products()->whereKeyNot($productKeys)->get()->each->delete();

            collect($request->input('data', []))
                ->each(function ($data) use ($order) {
                    $product = $order->products()->find($data['id']);
                    $product->update($data['attributes']);
                    $product->setTotalPrice();
                });

            $order->setTotalPrice();

            return new ResourceCollection($order->products()->get());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @param  \App\Models\OrderedProduct  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(
        Request $request,
        Order $order,
        OrderedProduct $product
    ) {
        $this->authorizeRequest($request, $order);
        $this->validateRequest($request, $order, $product);

        return DB::transaction(function () use ($request, $order, $product) {
            $product->update(Arr::only($request->input('data.attributes'), [
                'title',
                'description',
                'images',
                'price',
                'quantity',
            ]));

            $product->setTotalPrice();
            $order->setTotalPrice();

            return new Resource($product->fresh());
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $order)
    {
        if ($request->isFromMerchant()) {
            $merchant = optional($order->subscription)->merchant;

            if (!$merchant) {
                throw new UnauthorizedException;
            }

            $hasUser = $merchant->users()
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
     * @param  \App\Models\Order  $order
     * @param  \App\Models\OrderedProduct|null  $product
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $order, $product = null)
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
                'required',
                Rule::exists('ordered_product', 'id')
                    ->where('order_id', $order->getKey()),
            ],
            'data.*.attributes' => 'required',
            'data.*.attributes.title' => 'sometimes|string|max:255',
            'data.*.attributes.description' => 'sometimes|string|max:255',
            'data.*.attributes.images' => 'sometimes|array',
            'data.*.attributes.images.*' => 'url',
            'data.*.attributes.price' => 'sometimes|nullable|numeric|min:0.01',
            'data.*.attributes.quantity' => 'sometimes|integer|min:1',
        ]);
    }
}
