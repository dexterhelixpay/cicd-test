<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Bank;
use App\Models\Merchant;
use App\Models\MerchantPaymentType;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,null')->only('index', 'show');
        $this->middleware('auth:user')->only('update');
        $this->middleware('permission:CP: Settings - Payment Method Settings')->only('update');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $banks = QueryBuilder::for(Bank::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($banks);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Bank $bank)
    {
        return new Resource($bank);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.*.code' => [
                'required',
            ],
            'data.*.attributes' => 'required',

            'data.*.attributes.is_enabled' => 'sometimes',
        ]);

        return DB::transaction(function () use ($request) {
            $banks = collect($request->input('data'))
                ->map(function ($bank) {
                    Bank::query()
                        ->where('code', $bank['code'])
                        ->cursor()
                        ->tapEach(function($selectedBank) use ($bank) {
                            $selectedBank->update($bank['attributes'] ?? []);
                        })->all();

                    return $bank;
                });

            MerchantPaymentType::query()
                ->has('merchant')
                ->whereHas('paymentType', function ($query) {
                    return $query->where('name', 'Bank Transfer');
                })
                ->cursor()
                ->tapEach(function (MerchantPaymentType $merchantPaymentType) use ($banks) {
                    $filteredPaymentMethods = collect($merchantPaymentType->payment_methods)
                        ->map(function ($paymentMethod) use($banks) {
                            $selectedBank = collect($banks)->where('code', $paymentMethod['code'])->first();

                            $paymentMethod['is_globally_enabled'] = $selectedBank['attributes']['is_enabled'];
                            return $paymentMethod;
                        });

                    $merchantPaymentType->merchant->paymentTypes()->updateExistingPivot(
                        $merchantPaymentType->payment_type_id,
                        [
                            'sort_number' => $merchantPaymentType->sort_number,
                            'payment_methods' => $filteredPaymentMethods,
                            'is_enabled' => $merchantPaymentType->is_enabled,
                            'is_globally_enabled' => $merchantPaymentType->is_globally_enabled
                        ]
                    );
                })
                ->all();
            return $this->okResponse();
        });
    }

}
