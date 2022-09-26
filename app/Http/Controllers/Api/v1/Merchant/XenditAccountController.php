<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exceptions\BadRequestException;
use App\Facades\Xendit;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Libraries\Xendit\CallbackUrl;
use App\Libraries\Xendit\FeeRule;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class XenditAccountController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware([
            'auth:user',
            'permission:CP: Merchants - Xendit',
        ]);
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
        if ($merchant->xenditAccount()->exists()) {
            throw new BadRequestException('The merchant already has a Xendit account.');
        }

        $request->validate([
            'data.attributes.email' => 'required|email',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $response = Xendit::accounts()->create($request->input('data.attributes.email'));

            if ($response->failed()) {
                throw new BadRequestException(
                    $response->json('message') ?? $response->toException()->getMessage()
                );
            }

            $attributes = ['xendit_account_id' => $response->json('id')]
                + Arr::only($response->json(), ['email', 'status']);

            $response = Xendit::callbackUrls()->set(
                CallbackUrl::TYPE_EWALLET,
                route('api.v1.payments.xendit.events'),
                data_get($attributes, 'xendit_account_id')
            );

            if ($response->successful()) {
                $attributes['callback_token'] = $response->json('callback_token');
            }

            ($account = $merchant->xenditAccount()->make())
                ->forceFill($attributes)
                ->save();

            return new CreatedResource($account->fresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant)
    {
        if (!$account = $merchant->xenditAccount()->first()) {
            throw new BadRequestException("The merchant doesn't have a Xendit account.");
        }

        if (empty($request->input())) {
            return $this->updateStatus($account);
        }

        $feeUnit = $request->input('data.attributes.fee_unit');

        $request->validate([
            'data.attributes.fee_unit' => [
                'required',
                Rule::in(FeeRule::UNIT_FLAT, FeeRule::UNIT_PERCENT),
            ],
            'data.attributes.fee_amount' => [
                'required',
                'numeric',
                'min:0',
                Rule::when($feeUnit === FeeRule::UNIT_PERCENT, 'max:100', 'max:10000000'),
            ],
            'data.attributes.overall_paid_transactions_threshold' => [
                'required',
                'numeric',
                'min:0',
                'max:10000000',
            ],
        ]);

        $account->fill($request->input('data.attributes'));

        if ($account->isDirty(['fee_unit', 'fee_amount'])) {
            $response = Xendit::feeRules()->create(
                $merchant->name,
                $request->input('data.attributes.fee_unit'),
                $request->input('data.attributes.fee_amount'),
            );

            if ($response->failed()) {
                throw new BadRequestException(
                    $response->json('message') ?? $response->toException()->getMessage()
                );
            }

            $account->setAttribute('xendit_fee_rule_id', $response->json('id'));
        }

        $account->save();

        return new Resource($account->fresh());
    }

    /**
     * Update the status using Xendit API.
     *
     * @param  \App\Models\XenditAccount  $account
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateStatus($account)
    {
        $response = Xendit::accounts()->find($account->xendit_account_id);

        if ($response->failed()) {
            throw new BadRequestException(
                $response->json('message') ?? $response->toException()->getMessage()
            );
        }

        $account->forceFill(Arr::only($response->json(), 'status'))->save();

        return new Resource($account->fresh());
    }
}
