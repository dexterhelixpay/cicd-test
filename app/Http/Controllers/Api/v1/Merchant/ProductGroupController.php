<?php

namespace App\Http\Controllers\Api\v1\Merchant;

use App\Models\Customer;
use App\Models\Merchant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\Resource;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\MerchantProductGroup;
use App\Libraries\JsonApi\QueryBuilder;
use App\Http\Resources\ResourceCollection;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductGroupController extends Controller
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
        $this->middleware('permission:CP: Merchants - Edit|MC: Products')->only('store', 'update', 'destroy');
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
        $customer = null;
        if ($hasCustomerMembership = $request->has('filter.customer_has_membership')) {
            $customer = Customer::find($request->input('filter.customer_id'));
            if (
                !$customer
                || !optional($customer)->membership
                || ($customer->membership->completed_at !== null
                    && $customer->membership->cancelled_at !== null)
            ) {
                return response()->json(['data' => []]);
            }
        }

        $groups = QueryBuilder::for($merchant->productGroups()->getQuery(), 'product_groups')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($hasCustomerMembership, function ($query) use ($customer) {
                $query->whereHas('products', function ($query) use ($customer) {
                    $query->whereIn(
                        'id',
                        $customer
                            ->membershipProducts
                            ->pluck('product_id')
                            ->unique()
                            ->toArray()
                    );
                });
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($groups);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function store(Request $request, Merchant $merchant)
    {
        $this->authorizeRequest($request, $merchant);
        $this->validateRequest($request, $merchant);

        return DB::transaction(function () use ($request, $merchant) {
            $group = $merchant->productGroups()->make($request->input('data.attributes'));
            $group->sort_number = $merchant->productGroups()->max('sort_number') + 1;
            $group->forceFill([
                'slug' => Str::slug($group->name, '-')
            ]);

            if ($request->hasFile('data.attributes.group_banner')) {
                $group->uploadImage($request->file('data.attributes.group_banner'), 'group_banner_path');
            }

            if ($request->hasFile('data.attributes.icon')) {
                $group->uploadImage($request->file('data.attributes.icon'), 'icon_path');
            }

            $group->save();

            if (
                $request->filled('data.relationships.group_description_items.data')
                || $request->hasFile('data.relationships.group_description_items.data.*.attributes.icon')
            ) {
                $group->syncDescriptionItems(data_get($request, 'data.relationships.group_description_items.data'));
            }

            $productGroups = collect($request->input('data.relationships.products.data') ?? [])
                ->mapWithKeys(function ($product, $index) {
                    return [$product['id'] => ['sort_number' => $index + 1]];
                })
                ->toArray();


            $group->products()->sync($productGroups);

            return new Resource($group->fresh());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $productGroup
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Merchant $merchant, int $productGroup)
    {
        $productGroup = QueryBuilder::for(MerchantProductGroup::class)
            ->whereKey($productGroup)
            ->apply()
            ->first();

        if (!$productGroup) throw (new ModelNotFoundException)->setModel(MerchantProductGroup::class);

        return new Resource($productGroup);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $productGroup
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Merchant $merchant, int $productGroup)
    {
        $productGroup = $merchant->productGroups()->findOrFail($productGroup);
        $this->validateRequest($request, $merchant, $productGroup);


        return DB::transaction(function () use ($request, $productGroup) {
            $productGroup->fill($request->input('data.attributes'));

            if ($request->filled('data.attributes.group_banner_video_link')) {
                $productGroup->forceFill(['group_banner_path' => null]);
            }

            if ($request->hasFile('data.attributes.group_banner')) {
                $productGroup->uploadImage($request->file('data.attributes.group_banner'), 'group_banner_path');
            } else if ($request->has('data.attributes.group_banner_path')) {
                $productGroup->forceFill([
                    'group_banner_path' => $request->input('data.attributes.group_banner_path')
                ]);
            } elseif (
                $request->has('data.attributes.group_banner')
                && is_null($request->input('data.attributes.group_banner'))
            ) {
                $productGroup->forceFill(['group_banner_path' => null]);
            }

            if ($request->hasFile('data.attributes.icon')) {
                $productGroup->uploadImage($request->file('data.attributes.icon'), 'icon_path');
            } else if ($request->has('data.attributes.icon_path')) {
                $productGroup->forceFill([
                    'icon_path' => $request->input('data.attributes.icon_path')
                ]);
            } elseif (
                $request->has('data.attributes.icon')
                && is_null($request->input('data.attributes.icon'))
            ) {
                $productGroup->forceFill(['icon_path' => null]);
            }

            $productGroup->update();

            if ($request->hasOnly('is_visible', 'data.attributes')) return;

            if (
                $request->filled('data.relationships.group_description_items.data')
                || $request->hasFile('data.relationships.group_description_items.data.*.attributes.icon')
            ) {
                $productGroup->syncDescriptionItems(data_get($request, 'data.relationships.group_description_items.data'));
            } elseif (
                $request->has('data.relationships.group_description_items.data')
                && is_null($request->input('data.relationships.group_description_items.data'))
            ) {
                $productGroup->descriptionItems()->get()->each->delete();
            }

            if ($request->filled('data.relationships.products.data')) {
                $products = collect($request->input('data.relationships.products.data') ?? [])
                    ->mapWithKeys(function ($meta, $index) {
                        return [$meta['id'] => ['sort_number' => $index + 1]];
                    })
                    ->toArray();

                $productGroup->products()->sync($products);
            } else {
                $productGroup->products()->sync([]);
            }

            if ($productGroup->wasChanged('name')) {
                $productGroup->forceFill([
                    'slug' => Str::slug($productGroup->name, '-')
                ])
                    ->save();
            }

            return new Resource($productGroup->fresh());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  int  $productGroup
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Merchant $merchant, int $productGroup)
    {
        $productGroup = $merchant->productGroups()->find($productGroup);

        if (!optional($productGroup)->delete()) {
            throw (new ModelNotFoundException)->setModel(MerchantProductGroup::class);
        }

        return response()->json([], 204);
    }

    /**
     * Validate the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Merchant  $merchant
     * @param  \App\Models\MerchantProductGroup|null  $merchantProductGroup
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, $merchant, $merchantProductGroup = null)
    {
        if ($merchantProductGroup) {
            return $request->validate([
                'data' => 'required',
                'data.attributes' => 'required',
                'data.attributes.icon' => 'nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',
                'data.attributes.group_banner' => 'nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',

                'data.attributes.is_visible' => 'sometimes|boolean',
                'data.attributes.name' => [
                    'sometimes',
                    'string',
                    Rule::unique('merchant_product_groups', 'name')
                        ->when($merchant, function ($rule, $merchant) {
                            $rule->where('merchant_id', $merchant->getKey());
                        })
                        ->when($merchantProductGroup, function ($rule, $group) {
                            $rule->ignore($group);
                        }),
                ],
                'data.relationships.group_description_items.data.*.attributes.icon' => 'nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',
                'data.relationships.group_description_items.data.*.attributes.description' => 'required|string',
                'data.relationships.products.data' => 'sometimes|nullable|array',
                'data.relationships.products.data.*.id' => [
                    'sometimes',
                    Rule::exists('products', 'id'),
                ],
            ]);
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'required',

            'data.attributes.icon' => 'nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',
            'data.attributes.group_banner' => 'nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',

            'data.attributes.is_visible' => 'required|boolean',
            'data.attributes.name' => [
                'required',
                'string',
                Rule::unique('merchant_product_groups', 'name')
                    ->when($merchant, function ($rule, $merchant) {
                        $rule->where('merchant_id', $merchant->getKey());
                    })
            ],

            'data.relationships.products.data' => 'sometimes|nullable|array',
            'data.relationships.products.data.*.id' => [
                'required',
                Rule::exists('products', 'id'),
            ],

            'data.relationships.group_description_items.data.*.attributes.icon' => 'sometimes|nullable|mimes:gif,jpg,jpeg,png,webp|max:2000',
            'data.relationships.group_description_items.data.*.attributes.emoji' => 'sometimes|nullable|string',
            'data.relationships.group_description_items.data.*.attributes.description' => 'required|string',
        ]);
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
                Rule::exists('merchant_product_groups', 'id'),
            ],
            'data.*.attributes' => 'required',

            'data.*.attributes.sort_number' => 'sometimes|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $merchant) {
            $productGroups = collect($request->input('data'))
                ->map(function ($productGroup) use ($merchant) {
                    $productGroupModel = $merchant->productGroups()->find($productGroup['id']);
                    $productGroupModel->update($productGroup['attributes'] ?? []);

                    return $productGroupModel->fresh();
                });

            return new ResourceCollection($productGroups);
        });
    }
}
