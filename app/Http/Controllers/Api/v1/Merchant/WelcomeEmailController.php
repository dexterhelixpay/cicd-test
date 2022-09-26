<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Merchant;
use Illuminate\Support\Arr;
use App\Models\WelcomeEmail;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WelcomeEmailController extends Controller
{

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant');
        $this->middleware('permission:CP: Merchants - Edit|MC: Customers');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $welcomeEmails = QueryBuilder::for($merchant->welcomeEmails()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($welcomeEmails);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $welcomeEmail = $merchant->welcomeEmails()->make($request->input('data.attributes'));

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $welcomeEmail->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $welcomeEmail->save();

            if ($request->has('data.relationships.products.data')) {
                $welcomeEmail->products()->sync(
                    collect($request->input('data.relationships.products.data'))
                        ->pluck('id')
                );
            }

            return new CreatedResource($welcomeEmail->refresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, WelcomeEmail $welcomeEmail)
    {
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $welcomeEmail) {
            $welcomeEmail->update($request->input('data.attributes'));

            if ($request->hasFile('data.attributes.banner_image_path')) {
                $welcomeEmail->uploadImage($request->file('data.attributes.banner_image_path'));
            }

            $welcomeEmail->save();

            if ($request->has('data.relationships.products.data')) {
                $welcomeEmail->products()->sync(
                    collect($request->input('data.relationships.products.data'))
                        ->pluck('id')
                );
            }

            return new CreatedResource($welcomeEmail->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $welcomeEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $welcomeEmail)
    {
        $welcomeEmail = QueryBuilder::for($merchant->welcomeEmails()->getQuery())
            ->whereKey($welcomeEmail)
            ->apply()
            ->first();

        if (!$welcomeEmail) {
            throw (new ModelNotFoundException())->setModel(WelcomeEmail::class);
        }

        return new Resource($welcomeEmail);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $welcomeEmail
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Merchant $merchant, $welcomeEmail)
    {
        $welcomeEmail = $merchant->welcomeEmails()->find($welcomeEmail);

        if (!optional($welcomeEmail)->delete()) {
            throw (new ModelNotFoundException)->setModel(WelcomeEmail::class);
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
        if ($request->method() == "POST") {
            $request->validate([
                'data.attributes.banner_image_path' => 'nullable|image'
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.subject' => 'required|string|max:255',
            'data.attributes.title' => 'required|string|max:255',
            'data.attributes.subtitle' => 'required|string',
            'data.attributes.body' => 'nullable|sometimes|string',
            'data.attributes.banner_url' => 'nullable|sometimes|string|max:255',

            'data.relationships.products.data' => 'nullable',
        ]);
    }
}
