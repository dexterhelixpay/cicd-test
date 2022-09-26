<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $shippingMethods = QueryBuilder::for(ShippingMethod::class)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($shippingMethods);
    }
}
