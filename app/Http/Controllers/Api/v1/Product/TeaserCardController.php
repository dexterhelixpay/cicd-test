<?php

namespace App\Http\Controllers\Api\v1\Product;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Product;
use App\Models\ProductTeaserCard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeaserCardController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth.client:user,merchant');
        $this->middleware('permission:CP: Merchants - Edit|MC: Products');
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Product $product)
    {
        $items = QueryBuilder::for($product->teaserCards()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($items);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Product $product)
    {
        $this->validateRequest($request, $product);

        return DB::transaction(function () use ($request, $product) {
            $teaserCard = $product->teaserCards()->make($request->input('data.attributes'));
            $teaserCard->sort_number = $product->teaserCards()->max('sort_number') + 1;

            if ($request->hasFile('data.attributes.thumbnail')) {
                $teaserCard->uploadThumbnail(
                    $request->file('data.attributes.thumbnail')
                );
            }

            $teaserCard->save();

            return new CreatedResource($teaserCard->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $teaserCard
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Product $product, $teaserCard)
    {
        $teaserCard = QueryBuilder::for($product->teaserCards()->getQuery())
            ->whereKey($teaserCard)
            ->apply()
            ->first();

        if (!$teaserCard) {
            throw (new ModelNotFoundException)->setModel(ProductTeaserCard::class);
        }

        return new Resource($teaserCard);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $teaserCard
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product, $teaserCard)
    {
        $this->validateRequest(
            $request,
            $product,
            $teaserCard = $product->teaserCards()->findOrFail($teaserCard)
        );

        return DB::transaction(function () use ($request, $teaserCard) {
            $teaserCard->forceFill($request->input('data.attributes', []));

            if ($request->hasFile('data.attributes.thumbnail')) {
                $teaserCard->uploadThumbnail(
                    $request->file('data.attributes.thumbnail'),
                );
            } elseif (
                $request->has('data.attributes.thumbnail')
                && is_null($request->input('data.attributes.thumbnail'))
            ) {
                $teaserCard->forceFill(['thumbnail_path' => null]);
            }

            $teaserCard->update();

            return new Resource($teaserCard->fresh());
        });
    }

      /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  int  $teaserCard
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Product $product, $teaserCard)
    {
        $teaserCard = $product->teaserCards()->find($teaserCard);

        if (!optional($teaserCard)->delete()) {
            throw (new ModelNotFoundException)->setModel(ProductTeaserCard::class);
        }

        return response()->json([], 204);
    }

    /**
     * Update the specified resources in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdate(Request $request, Product $product)
    {
        $request->validate([
            'data' => 'required',
            'data.*.id' => [
                'required',
                Rule::exists('product_teaser_cards', 'id'),
            ],
            'data.*.attributes' => 'required',

            'data.*.attributes.sort_number' => 'sometimes|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $product) {
            $teaserCards = collect($request->input('data'))
                ->map(function ($teaserCard) use ($product) {
                    $teaserCardModel = $product->teaserCards()->find($teaserCard['id']);
                    $teaserCardModel->update($teaserCard['attributes'] ?? []);

                    return $teaserCardModel->fresh();
                });

            return new ResourceCollection($teaserCards);
        });
    }

     /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  \App\Models\ProductTeaserCard|null  $teaserCard
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $product, $teaserCard = null)
    {
        if ($teaserCard) {
            return $request->validate([
                'data.attributes.headline' => 'sometimes',
                'data.attributes.subheader' => 'sometimes',

                'data.attributes.video_link' => 'nullable',
                'data.attributes.thumbnail_path' => 'nullable',

                'data.attributes.is_visible' => 'sometimes|boolean',
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.headline' => 'required',
            'data.attributes.subheader' => 'required',

            'data.attributes.video_link' => 'nullable',
            'data.attributes.thumbnail_path' => 'nullable',

            'data.attributes.is_visible' => 'required|boolean',
        ]);
    }
}
