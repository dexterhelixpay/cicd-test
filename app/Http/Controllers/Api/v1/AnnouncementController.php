<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Merchant;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
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
        // $this->middleware('permission:CP: Merchants - Edit|MC: Customers');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $announcements = QueryBuilder::for(Announcement::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($announcements);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $this->validateRequest($request);

        return DB::transaction(function () use ($request) {
            $announcement = Announcement::make($request->input('data.attributes'));

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $announcement->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            if (!data_get($request, 'data.attributes.is_draft')) {
                $announcement->is_published = true;
                $announcement->published_at = now();
            }

            $announcement->save();

            if ($request->filled('data.relationships.merchants.data')) {
                $announcement->merchants()->sync(
                    collect($request->input('data.relationships.merchants.data') ?? [])
                    ->mapWithKeys(function ($merchant) use ($request){
                        return [$merchant['id'] => ['expires_at' => data_get($request,'data.attributes.expires_at',null)]];
                    })
                    ->toArray()
                );
            }

            return new CreatedResource($announcement->refresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Announcement  $announcement
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Announcement $announcement)
    {
        $this->validateRequest($request);

        return DB::transaction(function () use ($request, $announcement) {
            $announcement->update($request->input('data.attributes'));

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $announcement->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $announcement->save();

            if ($request->filled('data.relationships.merchants.data')) {
                $announcement->merchants()->sync(
                    collect($request->input('data.relationships.merchants.data') ?? [])
                    ->mapWithKeys(function ($merchant) {
                        return [$merchant['id'] => ['expires_at' => data_get($merchant,'attributes.expires_at',null)]];
                    })
                    ->toArray()
                );
            }

            return new CreatedResource($announcement->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $announcement
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $announcement)
    {
        $announcement = QueryBuilder::for(Announcement::class)
            ->whereKey($announcement)
            ->apply()
            ->first();

        if (!$announcement) {
            throw (new ModelNotFoundException())->setModel(Announcement::class);
        }

        return new Resource($announcement);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $announcement
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($announcement)
    {
        $announcement = Announcement::find($announcement);

        if (!optional($announcement)->delete()) {
            throw (new ModelNotFoundException)->setModel(Announcement::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\WelcomeEmail|null  $welcomeEmail
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request)
    {
        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',
            'data.attributes.title' => 'required|string|max:255',
            'data.attributes.subtitle' => 'required|string',
            'data.attributes.banner_image_path' => 'nullable|sometimes|image',
            'data.attributes.banner_image_url' => 'nullable|sometimes|string|max:255',
        ]);
    }
}
