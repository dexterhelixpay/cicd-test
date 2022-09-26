<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\MerchantFinance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store', 'update', 'destroy');
        $this->middleware('auth:user,merchant,null')->only('index', 'show');
        $this->middleware('permission:CP: Merchants - Manage Finances|MC: Finances')->only('store', 'update', 'destroy');
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
        $finances = QueryBuilder::for($merchant->finances()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($finances);
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
            $finance = $merchant->finances()->make($request->input('data.attributes'));
            $finance->save();

            return new CreatedResource($finance->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $finance
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $finance)
    {
        $finance = QueryBuilder::for($merchant->finances()->getQuery())
            ->whereKey($finance)
            ->apply()
            ->first();

        if (!$finance) {
            throw (new ModelNotFoundException)->setModel(MerchantFinance::class);
        }

        return new Resource($finance);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $finance
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $finance)
    {
        $this->validateRequest(
            $request,
            $merchant,
            $finance = $merchant->finances()->findOrFail($finance)
        );

        return DB::transaction(function () use ($request, $finance) {
            $finance->update($request->input('data.attributes', []));

            return new Resource($finance->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $finance
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $finance)
    {
        $finance = $merchant->products()->find($finance);

        if (!optional($finance)->delete()) {
            throw (new ModelNotFoundException)->setModel(MerchantFinance::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantFinance|null  $finance
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $finance = null)
    {
        if ($finance) {
            return $request->validate([

            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.remittance_date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'data.attributes.no_of_payments' => 'required|numeric|min:0',

            'data.attributes.total_value' => 'required|numeric|min:0',

            'data.attributes.google_link' => 'sometimes|nullable|url',
        ]);
    }
}
