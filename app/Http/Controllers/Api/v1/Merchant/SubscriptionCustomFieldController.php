<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Merchant;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\SubscriptionCustomField;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SubscriptionCustomFieldController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store','destroy');
        $this->middleware('auth:user,merchant,null')->only('index','show');
        $this->middleware('permission:CP: Merchants - Edit|MC: Custom Fields')->only('store', 'destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     * @param  \App\Models\Merchant  $merchant
     * @return \App\Http\Resources\ResourceCollection;
     */
    public function index(Merchant $merchant)
    {
        $customFields = QueryBuilder::for(
                $merchant->subscriptionCustomFields()
                    ->getQuery()
            )
            ->apply()
            ->fetch();

        return new ResourceCollection($customFields);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Merchant $merchant, $customField)
    {
        $customField = QueryBuilder::for(
                $merchant->subscriptionCustomFields()
                    ->getQuery()
            )
            ->whereKey($customField)
            ->apply()
            ->first();

        if (!$customField) {
            throw (new ModelNotFoundException)->setModel(SubscriptionCustomField::class);
        }

        return new Resource($customField);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \App\Http\Resources\ResourceCollection;
     */
    public function store(Request $request, Merchant $merchant)
    {

        DB::transaction(function () use ($request, $merchant) {
            // $merchant->syncSubsCustomFields($request->input('data'));

            $merchant->subscriptionCustomFields()
                ->make($request->input('data.attributes'))
                ->setAttribute('code', Str::camel($request->input('data.attributes.label')))
                ->save();
        });

        return new ResourceCollection($merchant->subscriptionCustomFields);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \App\Http\Resources\ResourceCollection;
     */
    public function update(Request $request, Merchant $merchant, $customField)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
        ]);

        DB::transaction(function () use ($request, $customField) {
            $customField = SubscriptionCustomField::findOrFail($customField);

            if (
                $request->hasOnly('is_visible', 'data.attributes')
                || $request->hasOnly('is_required', 'data.attributes')
            ) {
                return $customField->update($request->input('data.attributes'));
            }

            $customField->update(
                    $request->input('data.attributes')
                    + ['code' => Str::camel($request->input('data.attributes.label'))]
            );

        });

        return new ResourceCollection($merchant->subscriptionCustomFields);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SubscriptionCustomField  $subscriptionCustomField
     * @return \Illuminate\Http\Response
     */
    public function destroy(Merchant $merchant, $customField)
    {
        $customField = SubscriptionCustomField::findOrFail($customField);

        $merchant->subscriptions()
            ->whereNotNull('other_info')
            ->get()
            ->each(function($subscription) use ($customField){
                $otherInfo = is_string($subscription->other_info)
                    ? json_decode($subscription->other_info, true)
                    : $subscription->other_info;

                if (in_array($customField->code, array_column($otherInfo, 'code')) ) {
                    abort(422, "Custom field is already used by the customer");
                }
            });

        $customField->delete();

        return response()->json([], 204);
    }
}
