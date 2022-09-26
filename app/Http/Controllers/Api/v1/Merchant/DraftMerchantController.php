<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Country;
use Illuminate\Http\Request;
use App\Models\DraftMerchant;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use App\Http\Requests\DraftMerchantRequest;

class DraftMerchantController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $merchants = QueryBuilder::for(DraftMerchant::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($merchants);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(DraftMerchantRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $country = Country::find($request->input('data.attributes.country_id'));
            $merchant = DraftMerchant::make($request->input('data.attributes'))
                ->fill([
                    'formatted_mobile_number' => "{$country->dial_code}{$request->input('data.attributes.mobile_number')}"
                ]);
            $merchant->save();

            return new CreatedResource($merchant);
        });
    }

}
