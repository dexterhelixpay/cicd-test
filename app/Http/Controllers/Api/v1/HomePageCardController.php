<?php

namespace App\Http\Controllers\Api\v1;

use App\Traits\HasAssets;
use Illuminate\Support\Arr;
use App\Models\HomePageCard;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Requests\HomePageCardRequest;
use App\Http\Resources\ResourceCollection;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class HomePageCardController extends Controller
{

    use HasAssets;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('index');
        $this->middleware('auth:user')->only('show','store','update','sort','destroy');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $cards = QueryBuilder::for(HomePageCard::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($cards);
    }

    /**
     * Get merchant visible cards
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array||object  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public static function getMerchantCards(Request $request, Merchant $merchant)
    {
        $cards = QueryBuilder::for(HomePageCard::class)
            ->where('is_enabled', true)
            ->whereRaw("restricted_merchant_ids IS NULL OR JSON_CONTAINS(restricted_merchant_ids, '\"" . $merchant->id . "\"') = 0")
            ->orderBy('sort_number')
            ->latest()
            ->take(12)
            ->apply()
            ->fetch();

        return new ResourceCollection($cards);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\HomePageCardRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(HomePageCardRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $image = null;
            if ($request->hasFile('data.attributes.image_path')) {
                $image = HomePageCard::uploadImage($request->file('data.attributes.image_path'));
            }

            $card = HomePageCard::make(
                    Arr::except($request->input('data.attributes'), 'image_path')
                )
                ->forceFill([
                    'image_path' => $image
                ]);

            $card->save();

            return new CreatedResource($card->refresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\HomePageCardRequest  $request
     * @param  int  $card
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(HomePageCardRequest $request, int $card)
    {
        $card = HomePageCard::findOrFail($card);

        return DB::transaction(function () use ($request, $card) {
            $image = $card->getRawOriginal('image_path') ?? null;
            if ($request->hasFile('data.attributes.image_path')) {
                $image = HomePageCard::uploadImage($request->file('data.attributes.image_path'));
            }

            $card->fill(Arr::except($request->input('data.attributes'), 'image_path'))
                ->forceFill([
                    'image_path' => $image
                ]);

            $card->update();

            return new CreatedResource($card->refresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $card
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $card)
    {
        $card = QueryBuilder::for(HomePageCard::class)
            ->whereKey($card)
            ->apply()
            ->first();

        if (!$card) {
            throw (new ModelNotFoundException())->setModel(HomePageCard::class);
        }

        return new Resource($card);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $card
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $card)
    {
        $card = HomePageCard::find($card);

        if (!optional($card)->delete()) {
            throw (new ModelNotFoundException)->setModel(HomePageCard::class);
        }

        return response()->json([], 204);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\HomePageCardRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sort(HomePageCardRequest $request)
    {
        return DB::transaction(function () use ($request) {
            collect($request->input('data.attributes.cards'))
                ->each(function($card, $index) {
                   HomePageCard::whereKey(data_get($card, 'id'))
                        ->update(['sort_number' => $index + 1]);
                });

            return new ResourceCollection(
                HomePageCard::orderBy('sort_number')
                    ->get()
            );
        });
    }
}
