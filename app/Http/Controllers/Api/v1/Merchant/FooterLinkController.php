<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FooterLinkController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('update');
        $this->middleware('auth:user,merchant,null')->only('show','store','');
        $this->middleware(
                'permission:CP: Merchants - Edit|MC: Settings|CP: Merchants - Log in to Store'
            )
            ->only('update');
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
        $items = QueryBuilder::for($merchant->footerLinks()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($items);
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
            $footerLink = $merchant->footerLinks()->make($request->input('data.attributes'));
            $footerLink->sort_number = $merchant->footerLinks()->max('sort_number') + 1;

            if ($request->hasFile('data.attributes.icon')) {
                $footerLink->uploadIcon(
                    $request->file('data.attributes.icon')
                );
            }

            $footerLink->save();

            return new CreatedResource($footerLink->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $footerLink
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $footerLink)
    {
        $footerLink = QueryBuilder::for($merchant->footerLinks()->getQuery())
            ->whereKey($footerLink)
            ->apply()
            ->first();

        if (!$footerLink) {
            throw (new ModelNotFoundException)->setModel(SocialLink::class);
        }

        return new Resource($footerLink);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $footerLink
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $footerLink)
    {
        $this->validateRequest(
            $request,
            $merchant,
            $footerLink = $merchant->footerLinks()->findOrFail($footerLink)
        );

        return DB::transaction(function () use ($request, $footerLink) {
            $footerLink->forceFill($request->input('data.attributes', []));

            if ($request->hasFile('data.attributes.icon')) {
                $footerLink->uploadIcon(
                    $request->file('data.attributes.icon'),
                );
            } elseif (
                $request->has('data.attributes.icon')
                && is_null($request->input('data.attributes.icon'))
            ) {
                $footerLink->forceFill(['thumbnail_path' => null]);
            }

            $footerLink->update();

            return new Resource($footerLink->fresh());
        });
    }

      /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $footerLInk
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $footerLInk)
    {
        $footerLInk = $merchant->footerLinks()->find($footerLInk);

        if (!optional($footerLInk)->delete()) {
            throw (new ModelNotFoundException)->setModel(SocialLink::class);
        }

        return response()->json([], 204);
    }


    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Merchant $merchant)
    {
        $request->validate([
            'data' => 'required',
            'data.*.id' => [
                'required',
                Rule::exists('social_links', 'id'),
            ],
            'data.*.attributes' => 'required',
            'data.*.attributes.sort_number' => 'sometimes|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $footers = collect($request->input('data'))
                ->map(function ($footer) use ($merchant) {
                    $footerModel = $merchant->footerLinks()->find($footer['id']);
                    $footerModel->update($footer['attributes'] ?? []);

                    return $footerModel->fresh();
                });

            return new ResourceCollection($footers);
        });
    }

     /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\SocialLink|null  $socialLink
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $socialLink = null)
    {
        if ($socialLink) {
            return $request->validate([
                'data.attributes.icon' => 'sometimes',
                'data.attributes.is_footer' => 'sometimes|boolean',
                'data.attributes.is_visible' => 'sometimes|boolean',
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.icon' => 'required',
            'data.attributes.is_footer' => 'required',

            'data.attributes.is_visible' => 'required|boolean',
        ]);
    }
}

