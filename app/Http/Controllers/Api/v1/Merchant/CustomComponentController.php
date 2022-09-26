<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomComponentRequest;
use App\Models\MerchantProductGroup;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use App\Models\CustomComponent;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class CustomComponentController extends Controller
{
    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:user,merchant')->only('store', 'update', 'destroy');
        $this->middleware('auth:user,merchant,null')->only('index', 'show');
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
        $groups = QueryBuilder::for($merchant->customComponents()->getQuery(), 'custom_components')
            ->apply()
            ->fetch();

        return new ResourceCollection($groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\CustomComponentRequest $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function store(CustomComponentRequest $request, Merchant $merchant)
    {
        return DB::transaction(function () use ($request, $merchant) {
            $customComponent = $merchant->customComponents()->make($request->input('data.attributes'));
            $customComponent->sort_number = $merchant->customComponents()->max('sort_number') + 1;
            $customComponent->save();

            $customComponent->customFields()->saveMany(
                collect($request->input('data.relationships.fields.data') ?? [])
                    ->pluck('attributes')
                    ->map(function ($field, $index) use ($customComponent) {
                        $customField = $customComponent->customFields()
                            ->make($field);
                        $customField->code = Str::camel(data_get($field, 'label'));
                        $customField->sort_number = $index + 1;

                        return $customField;
                    })
            );

            return new Resource($customComponent->fresh('customFields'));
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customComponent
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Merchant $merchant, int $customComponent)
    {
        $customComponent = QueryBuilder::for(CustomComponent::class)
            ->whereKey($customComponent)
            ->apply()
            ->first();

        if (!$customComponent) throw (new ModelNotFoundException)->setModel(CustomComponent::class);

        return new Resource($customComponent);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customComponent
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, int $customComponent)
    {
        $customComponent = $merchant->customComponents()->findOrFail($customComponent);

        if (
            $request->hasOnly('is_visible' , 'data.attributes')
            || $request->hasOnly('is_default', 'data.attributes')
        ) {
            return $this->updateStatus($request, $customComponent);
        }

        return DB::transaction(function () use ($request, $customComponent) {
            $customComponent->update($request->input('data.attributes'));

            if ($request->filled('data.relationships.fields.data')) {
                $fields = collect($request->input('data.relationships.fields.data') ?? [])
                    ->map(function ($field, $index) {
                        $label = data_get($field, 'attributes.label');

                        return [
                            'id' => data_get($field, 'id'),
                            'sort_number' => $index + 1,
                            'code' => data_get($field, 'attributes.is_default')
                                ? data_get($field, 'attributes.code')
                                :  Str::camel(data_get($field, 'attributes.label'))
                        ] + $field['attributes'];
                    })
                    ->toArray();

                $customComponent->customFields()->sync($fields);
            } else {
                $customComponent->customFields()->get()->each->delete();
            }

            return new Resource($customComponent->fresh('customFields'));
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $customComponent
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, int $customComponent)
    {
        $customComponent = $merchant->customComponents()->find($customComponent);

        if (!optional($customComponent)) {
            throw (new ModelNotFoundException)->setModel(CustomComponent::class);
        }

        $customComponent->delete();
        $customComponent->customFields()->get()->each->delete();

        return response()->json([], 204);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CustomComponent  $customComponent
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateStatus(Request $request, CustomComponent $customComponent)
    {
        return DB::transaction(function () use ($request, $customComponent) {
            $customComponent->update($request->input('data.attributes', []));

            return new Resource($customComponent->fresh('customFields'));
        });
    }

}
