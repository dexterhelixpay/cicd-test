<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Http\Resources\CreatedResource;
use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Http\Resources\Resource;
use App\Http\Controllers\Controller;
use App\Http\Requests\MerchantMidRequest;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use App\Models\PaymayaMid;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class MidController extends Controller
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
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, Merchant $merchant)
    {
        $mids = QueryBuilder::for(PaymayaMid::class)
            ->apply()
            ->fetch();

        return new ResourceCollection($mids);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\MerchantMidRequest  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(MerchantMidRequest $request, Merchant $merchant)
    {
        return DB::transaction(function () use ($request, $merchant) {
            collect($request->input('data'))
                ->each(function ($data) use($merchant) {

                    if ($midId = data_get($data, 'id')) {
                        $mid = PaymayaMid::findOrFail($midId);

                        $publicKey = Str::contains($data['attributes']['public_key'], '•')
                            ? optional($mid)->getRawOriginal('public_key')
                            : $data['attributes']['public_key'];

                        $secretKey = Str::contains($data['attributes']['secret_key'], '•')
                            ? optional($mid)->getRawOriginal('secret_key')
                            : $data['attributes']['secret_key'];

                        $mid->forceFill(
                            Arr::except($data['attributes'], ['public_key', 'secret_key']) + [
                                'public_key' => $publicKey,
                                'secret_key' => $secretKey,
                                'merchant_id' => $merchant->id,
                            ]
                        )->update();

                        $mid->refresh();

                        $midType = Arr::has($data['attributes'], 'is_vault') ? 'vault' : 'pwp';

                        $midKeys = [
                                "paymaya_{$midType}_console_public_key" => optional($mid)->getRawOriginal('public_key'),
                                "paymaya_{$midType}_console_secret_key" => optional($mid)->getRawOriginal('secret_key'),
                            ];

                        $merchant->forceFill($midKeys)->saveQuietly();
                    } else {
                        $mid = PaymayaMid::make(
                            Arr::except($data['attributes'], ['public_key', 'secret_key'])
                        )
                        ->forceFill([
                            'merchant_id' => $merchant->id,
                            'public_key' => $data['attributes']['public_key'],
                            'secret_key' => $data['attributes']['secret_key'],
                            'is_console_created' => true
                        ]);

                        $mid->save();
                    }

                    $midType = Arr::has($data['attributes'], 'is_vault') ? 'vault' : 'pwp';

                    $midKeys = [
                        "paymaya_{$midType}_mid_console_id" => $mid->id,
                        "paymaya_{$midType}_console_public_key" => optional($mid)->getRawOriginal('public_key'),
                        "paymaya_{$midType}_console_secret_key" => optional($mid)->getRawOriginal('secret_key'),
                    ];

                    $merchant->forceFill($midKeys)->saveQuietly();
                });

            return new CreatedResource($merchant->fresh('paymayaPwpMid', 'paymayaVaultMid'));
        });
    }
}
