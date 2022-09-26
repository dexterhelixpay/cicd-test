<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Exceptions\BadRequestException;
use App\Facades\Xendit;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Libraries\Xendit\CallbackUrl;
use App\Libraries\JsonApi\QueryBuilder;
use App\Libraries\Xendit\FeeRule;
use App\Models\Merchant;
use App\Models\MerchantWebhookKey;
use App\Models\WebhookKey;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Support\Str;

class WebhookKeyController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);

        if (!$owner = $merchant->owner()->first()) {
            throw (new ModelNotFoundException)->setModel(MerchantWebhookKey::class);
        }

        $webhookKey = QueryBuilder::for($merchant->webhookKey()->getQuery())
            ->latest()
            ->first();

        if (!$webhookKey) {
            throw (new ModelNotFoundException)->setModel(MerchantWebhookKey::class);
        }

        return new Resource($webhookKey);
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
        return DB::transaction(function () use ($request, $merchant) {
            $timestamp = now()->toDateTimeString();

            $webhookKey = $merchant->webhookKey()->firstOrNew()
                ->forceFill([
                    'key' => Str::random(40).'=='
                ]);
            \Log::info($webhookKey);

            $webhookKey->save();

            return new CreatedResource($webhookKey->fresh());
        });
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
            && $merchant->getKey() != $request->userOrClient()->merchant_id
        ) {
            throw new UnauthorizedException;
        }
    }
}
