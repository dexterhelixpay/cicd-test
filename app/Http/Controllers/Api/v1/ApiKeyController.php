<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateApiKeyRequest;
use App\Http\Requests\ManageApiKeyRequest;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiKeyController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant');
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $keys = QueryBuilder::for(ApiKey::class)
            ->when($request->isFromMerchant(), function ($query, $user) {
                $query->where('merchant_id', $user->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($keys);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests\CreateApiKeyRequest  $request
     * @param  \App\Services\ApiKeyService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateApiKeyRequest $request, ApiKeyService $service)
    {
        return DB::transaction(function () use ($request, $service) {
            $key = $service->generate($request->merchant);

            return new CreatedResource($key);
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Http\Requests\ManageApiKeyRequest  $request
     * @param  \App\Models\ApiKey  $apiKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(ManageApiKeyRequest $request, ApiKey $apiKey)
    {
        return new Resource($apiKey->makeVisible('secret'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\ManageApiKeyRequest  $request
     * @param  \App\Models\ApiKey  $apiKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ManageApiKeyRequest $request, ApiKey $apiKey)
    {
        return DB::transaction(function () use ($request, $apiKey) {
            $apiKey->update($request->validated());

            return new Resource($apiKey);
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Http\Requests\ManageApiKeyRequest  $request
     * @param  \App\Models\ApiKey  $apiKey
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(ManageApiKeyRequest $request, ApiKey $apiKey)
    {
        if (!optional($apiKey)->delete()) {
            throw (new ModelNotFoundException)->setModel(ApiKey::class);
        }

        return response()->json([], 204);
    }
}
