<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\MerchantProductGroup;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:merchant,user,null')->only('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $groups = QueryBuilder::for(MerchantProductGroup::class)
            ->when($request->isFromMerchant(), function ($query, $merchantUser) {
                $query->where('merchant_id', $merchantUser->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($groups);
    }
}
