<?php

namespace App\Http\Controllers\Api\v1\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\Subscription;
use App\Models\Customer;
use App\Models\Order;
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
        $this->middleware('auth:user,merchant,customer');
        $this->middleware('permission:CP: Merchants - Edit|MC: Orders');
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
        $orders = QueryBuilder::for($subscription->orders()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($orders);
    }
}
