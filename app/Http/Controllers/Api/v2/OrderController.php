<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\PayOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('logged')->only('update');
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
        $orders = QueryBuilder::for(Order::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('subscription', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($orders);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $order)
    {
        $order = QueryBuilder::for(Order::class)
            ->whereKey($order)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->whereHas('subscription', function ($query) use ($user) {
                    $query->where('merchant_id', $user->merchant_id);
                });
            })
            ->apply()
            ->first();

        if (!$order) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return new Resource($order);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateOrderRequest  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateOrderRequest $request, Order $order)
    {
        return DB::transaction(function () use ($request, $order) {
            $order->update($request->validated());

            return new Resource($order->fresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\Order\PayOrderRequest  $request
     * @param  \App\Models\Product  $product
     * @param  \App\Services\PaymentService  $paymentService
     * @return \Illuminate\Http\JsonResponse
     */
    public function pay(PayOrderRequest $request, Order $order, PaymentService $paymentService)
    {
        $order = $paymentService->start($order);

        return new Resource($order->refresh());
    }
}
