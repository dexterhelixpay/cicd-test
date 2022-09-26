<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\PaymayaMid;
use Illuminate\Http\Request;

class PaymayaMidController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user');
        $this->middleware('permission:CP: Merchants - Manage PayMaya Keys');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $mids = QueryBuilder::for(PaymayaMid::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($mids);
    }
}
