<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\MerchantFollowUpEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;

class FollowUpController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,customer')->only('index', 'destroy');
        $this->middleware('auth:user,merchant,customer,null')->only('store', 'show', 'update');
        $this->middleware('permission:CP: Merchants - Manage Follow-up Emails|MC: Follow-up Emails')->only('index', 'destroy');
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
        $followUpEmails = QueryBuilder::for($merchant->followUpEmails()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($followUpEmails);
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
        $this->authorizeRequest($request, $merchant);
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $followUpEmail = $merchant->followUpEmails()
                ->make($request->input('data.attributes'));

            $followUpEmail->save();

            return new CreatedResource($followUpEmail->fresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $followUpEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $followUpEmail)
    {
        $followUpEmail = QueryBuilder::for($merchant->followUpEmails()->getQuery())
            ->whereKey($followUpEmail)
            ->apply()
            ->first();

        if (!$followUpEmail) {
            throw (new ModelNotFoundException)->setModel(MerchantFollowUpEmail::class);
        }

        return new Resource($followUpEmail);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $followUpEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $followUpEmail)
    {
        $this->authorizeRequest($request, $merchant);

        $this->validateRequest(
            $request,
            $merchant,
            $followUpEmail = $merchant->followUpEmails()->findOrFail($followUpEmail)
        );

        return DB::transaction(function () use ($request, $followUpEmail) {
            $followUpEmail->update($request->input('data.attributes', []));

            return new Resource($followUpEmail->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $followUpEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $followUpEmail)
    {
        $followUpEmail = $merchant->followUpEmails()->find($followUpEmail);

        if (!optional($followUpEmail)->delete()) {
            throw (new ModelNotFoundException)->setModel(MerchantFollowUpEmail::class);
        }

        return response()->json([], 204);
    }


     /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantUser|null  $user
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest($request, $merchant, $user = null)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
    }

      /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantFollowUpEmail|null  $followUpEmail
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $merchant, $followUpEmail = null)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
        ]);

        if ($followUpEmail) {
            return $request->validate([
                'data.attributes.days' => [
                    'required',
                    'max:180',
                    'min:1',
                    'max:180',
                    Rule::unique('merchant_follow_up_emails', 'days')
                        ->ignoreModel($followUpEmail)
                        ->where('merchant_id', $merchant->id)

                ],
                'data.attributes.subject' => 'sometimes|string',
                'data.attributes.headline' => 'sometimes|string',
                'data.attributes.body' => 'sometimes|string',

                'data.attributes.is_enabled' => 'sometimes|boolean',
            ]);
        }

        $request->validate([
            'data.attributes.days' => [
                'required',
                'min:1',
                'max:180',
                Rule::unique('merchant_follow_up_emails', 'days')
                    ->where('merchant_id', $merchant->id)
            ],
            'data.attributes.subject' => 'required|string',
            'data.attributes.headline' => 'required|string',
            'data.attributes.body' => 'required|string',
        ]);
    }


}
