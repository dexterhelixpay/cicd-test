<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Models\SubscriptionImport;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;

class SubscriptionImportController extends Controller
{

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:merchant,user');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $imports = QueryBuilder::for($merchant->subscriptionImports()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($imports);
    }
}
