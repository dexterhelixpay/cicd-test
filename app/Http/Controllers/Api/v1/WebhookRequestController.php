<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\WebhookRequest;

class WebhookRequestController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.key');
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $requests = QueryBuilder::for(WebhookRequest::class)
            ->apply()
            ->fetch(true);

        return new ResourceCollection($requests);
    }
}
