<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Province;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,null');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $provinces = QueryBuilder::for(Province::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($provinces);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Province  $province
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Province $province)
    {
        $province = QueryBuilder::for(Province::class)
            ->whereKey($province->getKey())
            ->apply()
            ->first();

        if (!$province) {
            throw (new ModelNotFoundException)->setModel(province::class);
        }

        return new Resource($province);
    }
}
