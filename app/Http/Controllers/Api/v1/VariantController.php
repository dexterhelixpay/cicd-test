<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use App\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;

class VariantController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,null')->only('index');
        $this->middleware('auth.client:user,merchant')->only('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $variants = QueryBuilder::for(ProductVariant::class)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->whereHas('product.merchant', function ($query) use ($request){
                    $query->where('merchant_id', $request->userOrClient()->merchant_id);
                });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($variants);
    }
}
