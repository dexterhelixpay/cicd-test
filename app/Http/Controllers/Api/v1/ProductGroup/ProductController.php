<?php

namespace App\Http\Controllers\Api\v1\ProductGroup;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\MerchantProductGroup;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,customer,null');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\MerchantProductGroup  $productGroup
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, MerchantProductGroup $productGroup)
    {
        $products = QueryBuilder::for($productGroup->products()->getQuery())
            ->when($request->input('search'), function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($products);
    }
}
