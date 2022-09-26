<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\CustomField;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomFieldController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user')->only('store','destroy');
        $this->middleware('auth:user,merchant,null')->only('index');
        $this->middleware('permission:CP: Merchants - Manage Custom Fields')->only('store','destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     * @param  \App\Models\Merchant  $merchant
     */
    public function index(Merchant $merchant)
    {
        $customFields = QueryBuilder::for($merchant->customFields()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($customFields);
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
        DB::transaction(function () use ($request, $merchant) {
            $merchant->syncCustomFields($request->input('data.attributes.custom_fields'));

            if ($request->filled('data.relationships.merchant.data.attributes')) {
                $merchant->update(
                    data_get($request, 'data.relationships.merchant.data.attributes')
                );
            }
        });

        return new ResourceCollection($merchant->customFields, CustomField::class);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Merchant $merchant, CustomField $customField)
    {
        $merchant->customers()
            ->each(function($customer) use ($customField){
                if (Arr::exists($customer->other_info ?: [], $customField->code)) {
                    abort(422, "Custom field is already used by the customer.");
                }
            });

        $customField->delete();

        return response()->json([], 204);
    }
}
