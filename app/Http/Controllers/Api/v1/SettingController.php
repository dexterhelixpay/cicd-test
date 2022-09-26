<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SettingController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant,null')->only('index', 'show');
        $this->middleware('auth:user')->only('update');
        // $this->middleware('permission:CP: Settings - Payment Method Settings')->only('update');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $settings = QueryBuilder::for(Setting::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($settings);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Setting  $setting
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Setting $setting)
    {
        return new Resource($setting);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\v2\Setting  $setting
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Setting $setting)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.value' => 'required|nullable',
        ]);

        $setting->update(
            Arr::only($request->input('data.attributes'), 'value')
        );

        return new Resource($setting->fresh());
    }
}
