<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store', 'update', 'destroy');
        $this->middleware('auth:user,merchant,customer,null')->only('index', 'show');
        $this->middleware('permission:CP: Merchants - Edit|MC: Products')->only('store', 'update', 'destroy');
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
        $products = QueryBuilder::for($merchant->products()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($products);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $product = $merchant->products()->make($request->input('data.attributes'));
            $product->sort_number = $merchant->products()->max('sort_number') + 1;
            $product->save();

            if ($request->hasFile('data.relationships.images.data.*.attributes.image')) {
                collect($request->file('data.relationships.images.data'))
                    ->pluck('attributes.image')
                    ->each(function ($image, $index) use ($product) {
                        $productImage = $product->images()->make();
                        $productImage->sort_number = $index + 1;

                        $productImage->uploadImage($image)->save();
                    });

                $product->load('images');
            }

            if ($request->has('data.relationships.description_items.data')) {
                $product->syncDescriptionItems(
                    data_get($request, 'data.relationships.description_items.data')
                );

                $product->load('items');
            }


            if ($request->has('data.relationships.recurrences.data')) {
                collect($request->input('data.relationships.recurrences.data'))
                    ->pluck('attributes')
                    ->each(function ($recurrence, $index) use ($product) {
                        $productRecurrence = $product->recurrences()->make($recurrence);
                        $productRecurrence->sort_number = $index + 1;

                        $productRecurrence->save();
                    });
            }

            return new CreatedResource($product->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $product)
    {
        $product = QueryBuilder::for($merchant->products()->getQuery())
            ->whereKey($product)
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $product)
    {
        $this->validateRequest(
            $request,
            $merchant,
            $product = $merchant->products()->findOrFail($product)
        );

        if (
            $request->hasOnly('are_multiple_orders_allowed', 'data.attributes')
            || $request->hasOnly('is_visible', 'data.attributes')
            || $request->hasOnly('is_membership', 'data.attributes')
            || $request->hasOnly('is_details_enabled', 'data.attributes')
            || $request->hasOnly('is_deep_link_enabled', 'data.attributes')
            || $request->hasOnly('is_shippable', 'data.attributes')
        ) {
            return $this->updateProductStatus($request, $product);
        }

        return DB::transaction(function () use ($request, $product) {
            $product->update($request->input('data.attributes', []));

            if (
                $request->filled('data.relationships.images.data')
                || $request->hasFile('data.relationships.images.data.*.attributes.image')
            ) {
                $images = $request->input('data.relationships.images.data', [])
                    + $request->file('data.relationships.images.data', []);

                $product->syncImages($images);
            } elseif (
                $request->has('data.relationships.images.data')
                && is_null($request->input('data.relationships.images.data'))
            ) {
                $product->images()->get()->each->delete();
            }

            if ($request->filled('data.relationships.description_items.data')) {
                $product->syncDescriptionItems(
                    data_get($request, 'data.relationships.description_items.data')
                );
            } else {
                $product->items()->get()->each->delete();
            }

            if ($request->has('data.relationships.recurrences.data')) {
                $product->syncRecurrences(data_get($request, 'data.relationships.recurrences.data'));
            } else {
                $product->recurrences()->get()->each->delete();
            }

            return new Resource($product->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateProductStatus(Request $request, Product $product)
    {
        return DB::transaction(function () use ($request, $product) {
            $product->update($request->input('data.attributes', []));

            return new Resource($product->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $product)
    {
        $product = $merchant->products()->find($product);

        if (!optional($product)->delete()) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Product|null  $product
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $product = null)
    {
        if ($product) {
            return $request->validate([

            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.title' => 'required|string',
            'data.attributes.description' => 'nullable|string',

            'data.attributes.price' => 'nullable|numeric|min:0',

            'data.attributes.are_multiple_orders_allowed' => 'required|boolean',
            'data.attributes.is_visible' => 'sometimes|boolean',

            'data.relationships.images.data.*.attributes.image' => 'sometimes|image',
        ]);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Merchant $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.*.id' => [
                'required',
                Rule::exists('products', 'id'),
            ],
            'data.*.attributes' => 'required',

            'data.*.attributes.sort_number' => 'sometimes|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $products = collect($request->input('data'))
                ->map(function ($product) use ($merchant) {
                    $productModel = $merchant->products()->find($product['id']);
                    $productModel->update($product['attributes'] ?? []);

                    return $productModel->fresh();
                });

            return new ResourceCollection($products);
        });
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAll(Merchant $merchant)
    {
        if (app()->isProduction()) {
            return;
        }

        return DB::transaction(function () use ($merchant) {
            $merchant->products()->delete();

            return new ResourceCollection($merchant->products, Product::class);
        });
    }
}
