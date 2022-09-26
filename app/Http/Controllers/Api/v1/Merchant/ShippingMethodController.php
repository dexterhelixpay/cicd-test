<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Country;
use App\Models\Merchant;
use Illuminate\Http\Request;
use App\Models\ShippingMethod;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ShippingMethodController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store', 'destroy');
        $this->middleware('auth:user,merchant,null')->only('index','update');
        $this->middleware('permission:CP: Merchants - Edit|MC: Settings')->only('store', 'destroy');
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
        $shippingMethods = QueryBuilder::for($merchant->shippingMethods()->getQuery())
            ->apply()
            ->fetch();

        return new ResourceCollection($shippingMethods);
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $shippingMethod
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Merchant $merchant, $shippingMethod)
    {
        $shippingMethod = QueryBuilder::for($merchant->shippingMethods()->getQuery())
            ->whereKey($shippingMethod)
            ->apply()
            ->first();

        if (!$shippingMethod) {
            throw (new ModelNotFoundException())->setModel(ShippingMethod::class);
        }

        return new Resource($shippingMethod);
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $merchant)
    {
        if (
            $request->isFromMerchant()
            && $merchant->users()->whereKey($request->userOrClient()->getKey())->doesntExist()
        ) {
            throw new UnauthorizedException;
        }
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
        $this->authorizeRequest($request, $merchant);

        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $shippingMethod = $merchant->shippingMethods()->create($request->input('data.attributes'));

            if ($request->filled('data.relationships.countries.data', [])) {
                $shippingMethod->countries()->sync(
                    collect($request->input('data.relationships.countries.data', []))
                        ->pluck('id')
                );
            }

            if ($request->filled('data.relationships.provinces.data', [])) {
                $shippingMethod->provinces()->sync(
                    collect($request->input('data.relationships.provinces.data', []))
                        ->pluck('id')
                );
            } elseif ($shippingMethod->countries()->where('code', 'PH')->doesntExist()) {
                $shippingMethod->provinces()->detach();
            }

            return new CreatedResource($shippingMethod->fresh());
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $shippingMethod
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, $shippingMethod)
    {
        $this->authorizeRequest($request, $merchant);

        $this->validateRequest(
            $request,
            $merchant,
            $shippingMethod = $merchant->shippingMethods()->findOrFail($shippingMethod)
        );

        return DB::transaction(function () use ($request, $shippingMethod) {
            $shippingMethod->update($request->input('data.attributes'));

            if ($request->hasOnly('is_enabled', 'data.attributes')) return;

            if ($request->filled('data.relationships.countries.data', [])) {
                $shippingMethod->countries()->sync(
                    collect($request->input('data.relationships.countries.data', []))
                        ->pluck('id')
                );
            }

            if ($request->filled('data.relationships.provinces.data')) {
                $provinces = collect($request->input('data.relationships.provinces.data', []))
                    ->pluck('id');

                $shippingMethod->provinces()->sync($provinces);
            } elseif ($shippingMethod->countries()->where('code', 'PH')->doesntExist()) {
                $shippingMethod->provinces()->detach();
            }

            return new Resource($shippingMethod->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $shippingMethod
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, $shippingMethod)
    {
        $shippingMethod = $merchant->shippingMethods()->find($shippingMethod);

        if (!optional($shippingMethod)->delete()) {
            throw (new ModelNotFoundException)->setModel(ShippingMethod::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\ShippingMetho|null  $shippingMethod
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $shippingMethod = null)
    {
        if ($shippingMethod) {
           return $request->validate([
                'data' => 'required',
                'data.attributes' => 'required',

                'data.attributes.name' => [
                    'sometimes',
                    'string',

                    Rule::unique('shipping_methods', 'name')
                    ->where('merchant_id', $merchant->getKey())
                    ->ignore($shippingMethod),
                ],
                'data.attributes.description' => 'sometimes|string',

                'data.attributes.price' => 'sometimes|numeric|min:0',
                'data.attributes.is_enabled' => 'sometimes|boolean',

                'data.relationships.countries.data' => [
                    function ($attribute, $value, $fail) {
                        if (
                            count($value) > 1
                            && in_array(
                                Country::PHILIPPINES,
                                collect($value)->pluck('id')->toArray()
                            )
                        ) {
                            $fail('Philippines must not be assigned with another country.');
                        }
                    }
                ],

                'data.relationships.countries.data.*.id' => [
                    'exists:countries,id',
                    Rule::requiredIf($request->input('data.attributes.name') != 'International Delivery'),
                    function ($attribute, $value, $fail) use ($merchant, $shippingMethod)  {
                        $countries = $merchant->shippingMethods
                                ->pluck('countries')
                                ->flatten()
                                ->pluck('name','id');
                        if (
                            in_array(
                                $value,
                                $countries
                                ->filter(function ($value, $key) use ($shippingMethod){
                                    $countryIds = $shippingMethod->countries->pluck('id')->toArray();
                                    if (!in_array(Country::PHILIPPINES, $countryIds)) {
                                        array_push($countryIds, Country::PHILIPPINES);
                                    }
                                    return !in_array($key, $countryIds);
                                })
                                ->map(fn($country, $key) => $key)
                                ->toArray()
                            )
                        ) {
                            $fail("{$countries->get($value)} is already assigned to another shipping method");
                        }
                    }
                ],
                'data.relationships.provinces.data.*.id' => 'nullable|sometimes|exists:provinces,id',
            ],
            [
                'data.relationships.countries.data.*.id.not_in' => "Country id :values is already assigned to another shipping method"
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.name' => [
                'sometimes',
                'string',
                Rule::unique('shipping_methods', 'name')
                ->where('merchant_id', $merchant->getKey()),
            ],
            'data.attributes.description' => 'sometimes|string',

            'data.attributes.price' => 'sometimes|numeric|min:0',
            'data.attributes.is_enabled' => 'sometimes|boolean',

            'data.relationships.countries.data' => [
                function ($attribute, $value, $fail) {
                    if (
                        count($value) > 1
                        && in_array(
                            Country::PHILIPPINES,
                            collect($value)->pluck('id')->toArray()
                        )
                    ) {
                        $fail('Philippines must not be assigned with another country.');
                    }
                }
            ],

            'data.relationships.countries.data.*.id' => [
                'exists:countries,id',
                Rule::requiredIf($request->input('data.attributes.name') != 'International Delivery'),
                function ($attribute, $value, $fail) use ($merchant)  {
                    $countries = $merchant->shippingMethods
                            ->pluck('countries')
                            ->flatten()
                            ->pluck('name','id');
                    if (
                        in_array(
                            $value,
                            $countries->filter(function ($value, $key) {
                                    return $key !== Country::PHILIPPINES;
                                })
                                ->map(fn($country, $key) => $key)
                                ->toArray()
                        )
                    ) {
                        $fail("{$countries->get($value)} is already assigned to another shipping method");
                    }
                }
            ],
            'data.relationships.provinces.data.*.id' => 'nullable|sometimes|exists:provinces,id',
        ],
        [
            'data.relationships.countries.data.*.id.not_in' => "Country id :values is already assigned to another shipping method"
        ]);
    }
}
