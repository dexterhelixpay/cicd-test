<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateWebhookRequest;
use App\Http\Requests\ManageWebhookRequest;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Webhook;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
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
        $webhooks = QueryBuilder::for(Webhook::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch(true);

        return new ResourceCollection($webhooks);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CreateWebhookRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateWebhookRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $webhook = $request->merchant->webhooks()->create($request->validated());

            return new Resource($webhook->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Http\Requests\ManageWebhookRequest  $request
     * @param  \App\Models\Webhook  $webhook
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ManageWebhookRequest $request, Webhook $webhook)
    {
        if (!$webhook->delete()) {
            throw (new ModelNotFoundException)->setModel(Webhook::class);
        }

        return response()->json([], 204);
    }
}
