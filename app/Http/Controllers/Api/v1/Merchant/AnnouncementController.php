<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use App\Http\Controllers\Controller;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AnnouncementController extends Controller
{

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant,)
    {
        $announcements = QueryBuilder::for($merchant->announcements()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($announcements);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $announcement
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $announcement)
    {
        $announcement = QueryBuilder::for($merchant->announcements()->getQuery())
            ->whereKey($announcement)
            ->apply()
            ->first();

        if (!$announcement) {
            throw (new ModelNotFoundException())->setModel(Announcement::class);
        }

        return new Resource($announcement);
    }
}
