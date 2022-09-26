<?php

namespace App\Http\Controllers\Api\v1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;


class OrderController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,customer');
        $this->middleware('permission:CP: Merchants - Edit');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Customer $customer)
    {
        $orders = QueryBuilder::for($customer->orders()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($orders);
    }
}
