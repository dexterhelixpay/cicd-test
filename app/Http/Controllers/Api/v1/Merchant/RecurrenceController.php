<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Product;
use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use App\Models\MerchantRecurrence;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Validation\UnauthorizedException;

class RecurrenceController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('update');
        $this->middleware('auth:user,merchant,null')->only('index');
        $this->middleware('permission:CP: Merchants - Edit|MC: Products')->only('update');
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
        $recurrences = QueryBuilder::for($merchant->recurrences()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($recurrences);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $recurrence
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $recurrence)
    {
        $this->validateRequest($request, $merchant);

        $recurrence = $merchant->recurrences()->findOrfail($recurrence);

        return DB::transaction(function () use ($request, $recurrence, $merchant) {
            $originalName = $recurrence->name;
            $recurrence->update($request->input('data.attributes'));

            $merchant->products->each(function(Product $product) use ($recurrence, $originalName) {
                $product->variants()
                    ->where('title', 'asd')
                    ->first()
                    ?->fill(['title' => $recurrence->name])
                    ->save();
            });

            return new Resource($recurrence->fresh());
        });
    }


    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\Order|null  $order
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.name' => 'sometimes|string',
            'data.attributes.code' => 'sometimes|string',
            'data.attributes.description' => 'sometimes|string',
            'data.attributes.is_enabled' => 'sometimes|boolean',
        ]);
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }
}
