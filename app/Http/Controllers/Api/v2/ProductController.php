<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('logged')->only('update', 'destroy');
        $this->middleware('auth:api,user,merchant');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $products = QueryBuilder::for(Product::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($products);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $product)
    {
        $product = QueryBuilder::for(Product::class)
            ->whereKey($product)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->first();

        if (!$product) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return new Resource($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ProductRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProductRequest $request, Product $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $product->update($request->validated());

            return new Resource($product->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $product)
    {
        $product = Product::query()
            ->whereKey($product)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->first();

        if (!optional($product)->delete()) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return response()->json([], 204);
    }
}
