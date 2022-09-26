<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\SocialLinkIcon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SocialLinkIconController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant,null');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {

        $icons = QueryBuilder::for(SocialLinkIcon::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($icons);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $icon
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($icon)
    {
        $icon = QueryBuilder::for(SocialLinkIcon::class)
            ->whereKey($icon)
            ->apply()
            ->first();

        if (!$icon) {
            throw (new ModelNotFoundException)->setModel(SocialLinkIcon::class);
        }

        return new Resource($icon);
    }
}
