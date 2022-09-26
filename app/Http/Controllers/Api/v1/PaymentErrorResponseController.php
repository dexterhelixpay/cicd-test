<?php

namespace APp\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentErrorResponseRequest;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\PaymentErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PaymentErrorResponseController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');
        $this->middleware('permission:CP: Payment Settings - View');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $responses = QueryBuilder::for(PaymentErrorResponse::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($responses);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\PaymentErrorResponseRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(PaymentErrorResponseRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $response = PaymentErrorResponse::create(
                data_get($request->validated(), 'data.attributes')
            );

            return new CreatedResource($response->fresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\PaymentErrorResponseRequest  $request
     * @param  \App\Models\PaymentErrorResponse  $paymentErrorResponse
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PaymentErrorResponseRequest $request, PaymentErrorResponse $paymentErrorResponse)
    {
        return DB::transaction(function () use ($request, $paymentErrorResponse) {
            $paymentErrorResponse->update(
                data_get($request->validated(), 'data.attributes')
            );

            return new Resource($paymentErrorResponse->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Http\Requests\PaymentErrorResponseRequest  $request
     * @param  \App\Models\PaymentErrorResponse  $paymentErrorResponse
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PaymentErrorResponseRequest $request, PaymentErrorResponse $paymentErrorResponse)
    {
        if (!$paymentErrorResponse->delete()) {
            throw (new ModelNotFoundException)->setModel(PaymentErrorResponse::class);
        }

        return response()->json([], 204);
    }
}
