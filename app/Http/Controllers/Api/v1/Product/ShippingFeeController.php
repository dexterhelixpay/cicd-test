<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Exports\ShippingFee;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Product;
use App\Models\ProductTeaserCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use App\Models\Merchant;
use App\Models\MerchantUser;
use App\Models\ProductShippingFee;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShippingFeeController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant');
        $this->middleware('permission:CP: Merchants - Edit|MC: Products');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Product $product)
    {
        $items = QueryBuilder::for($product->shippingFees()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($items);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Product $product)
    {
        $this->validateRequest($request, $product);

        return DB::transaction(function () use ($request, $product) {
            $shippingFee = $product->shippingFees()->make($request->input('data.attributes'));
            $shippingFee->save();

            return new CreatedResource($shippingFee->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $shippingFee
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Product $product, $shippingFee)
    {
        $shippingFee = QueryBuilder::for($product->shippingFees()->getQuery())
            ->whereKey($shippingFee)
            ->apply()
            ->first();

        if (!$shippingFee) {
            throw (new ModelNotFoundException)->setModel(ProductShippingFee::class);
        }

        return new Resource($shippingFee);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $shippingFee
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product, $shippingFee)
    {
        $this->validateRequest(
            $request,
            $product,
            $shippingFee = $product->shippingFees()->findOrFail($shippingFee)
        );

        return DB::transaction(function () use ($request, $shippingFee) {
            $shippingFee->update($request->input('data.attributes', []));

            return new Resource($shippingFee->fresh());
        });
    }

      /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $shippingFee
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Product $product, $shippingFee)
    {
        $shippingFee = $product->shippingFees()->find($shippingFee);

        if (!optional($shippingFee)->delete()) {
            throw (new ModelNotFoundException)->setModel(ProductShippingFee::class);
        }

        return response()->json([], 204);
    }

 /**
     * Import the subscriptions for the given merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\ProductService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request, ProductService $service)
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

        $subscriptions = $service->importShippingFee($merchant, $request->file('data.attributes.file'));

        return $subscriptions;
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

        $export = new ShippingFee($merchant);

        return $export->download('Shipping Fee Template.xlsx');
    }

     /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  \App\Models\ProductShippingFee|null  $shippingFee
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $product, $shippingFee = null)
    {
        if ($shippingFee) {
            return $request->validate([
                'data' => 'sometimes',
                'data.attributes' => 'sometimes',

                'data.attributes.shipping_method_id' => [
                    'sometimes',
                    Rule::exists('shipping_methods', 'id')
                ],
                'data.attributes.first_item_price' => 'sometimes',
                'data.attributes.additional_quantity_price' => 'nullable',

                'data.attributes.is_enabled' => 'sometimes',
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.shipping_method_id' => [
                'required',
                Rule::exists('shipping_methods', 'id')
            ],
            'data.attributes.first_item_price' => 'required',
            'data.attributes.additional_quantity_price' => 'nullable',

            'data.attributes.is_enabled' => 'boolean',
        ]);
    }
}
