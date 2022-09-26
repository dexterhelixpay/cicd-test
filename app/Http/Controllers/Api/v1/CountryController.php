<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Country;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $countries = QueryBuilder::for(Country::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($countries);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $country
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($country)
    {
        $country = QueryBuilder::for(Country::class)
            ->whereKey($country)
            ->apply()
            ->first();

        if (!$country) {
            throw (new ModelNotFoundException)->setModel(Country::class);
        }

        return new Resource($country);
    }
}
