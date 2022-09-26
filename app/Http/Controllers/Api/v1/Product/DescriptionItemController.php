<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Product;
use App\Models\ProductDescriptionItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DescriptionItemController extends Controller
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
        $items = QueryBuilder::for($product->items()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($items);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Product $product)
    {
        $request->validate([
            'data' => 'required',
            'data.*.id' => [
                'sometimes',
                Rule::exists('product_description_items', 'id')
                    ->where('product_id', $product->getKey()),
            ],
            'data.*.attributes' => 'required',
            'data.*.attributes.bullet' => 'sometimes|nullable|image',
            'data.*.attributes.text' => 'required|string|max:64000',
        ]);

        $product->syncDescriptions(data_get($request->all(), 'data', []));

        return new ResourceCollection($product->items()->get());
    }
}
