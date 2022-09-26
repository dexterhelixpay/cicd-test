<?php

namespace App\Http\Controllers\Api\v1;

use App\Exceptions\BadRequestException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CreatedResource;
use App\Http\Resources\Resource;
use App\Http\Resources\ResourceCollection;
use App\Libraries\JsonApi\QueryBuilder;
use App\Models\Merchant;
use App\Models\Product;
use App\Models\ProductDeepLink;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\v2\ProductVariant as v2ProductVariant;
use App\Models\ProductRecurrence;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\UnauthorizedException;

class ProductController extends Controller
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
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $products = QueryBuilder::for(Product::class)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->apply()
            ->fetch();

        return new ResourceCollection($products);
    }

    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Services\ProductService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, ProductService $service)
    {
        $merchant = $this->validateRequest($request);

        return DB::transaction(function () use ($request, $service, $merchant) {
            $product = $merchant->products()->make(Arr::except($request->input('data.attributes'), ['slug', 'is_duplicate']));
            $product->sort_number = $merchant->products()->max('sort_number') + 1;
            $product->slug = $request->filled('data.attributes.slug')
                ? $this->setSlug(strtolower($request->input('data.attributes.slug')))
                : $this->setSlug($request->input('data.attributes.title'));

            if ($request->has('data.attributes.is_duplicate')) {
                $scheme = app()->isLocal() ? 'http' : 'https';
                $summaryUrl = config('bukopay.url.deep_link_summary');
                $originalUrl = "{$scheme}://{$merchant->subdomain}.{$summaryUrl}?product={$product->slug}";
                $product->deep_link = ProductDeepLink::createShortUrl($originalUrl);
            }

            $product->save();

            if ($request->filled('data.relationships.product_groups.data')) {
                $product->syncProductGroups(
                    data_get($request, 'data.relationships.product_groups.data')
                );
            } else {
                $product->groups()->sync([]);
            }

            $service->syncRecurrences(
                $product,
                $request->input('data.relationships.recurrences.data') ?: []
            );

            $service->syncOptions(
                $product,
                $request->input('data.relationships.options.data') ?: []
            );

            $service->syncVariants($product);
            $service->syncNewVariants($product);

            if ($request->filled('data.relationships.exclusive_product_groups.data')) {
                $product->syncExclusiveProductGroups(
                    data_get($request, 'data.relationships.exclusive_product_groups.data')
                );
            } else {
                $product->exclusiveGroups()->sync([]);
            }

            if ($request->hasFile('data.relationships.images.data.*.attributes.image')) {
                collect($request->file('data.relationships.images.data'))
                    ->pluck('attributes.image')
                    ->each(function ($image, $index) use ($product) {
                        $productImage = $product->images()->make();
                        $productImage->sort_number = $index + 1;

                        $productImage->uploadImage($image)->save();
                    });

                $product->load('images');
            }

            if ($request->has('data.relationships.images.data')) {
                collect($request->input('data.relationships.images.data'))
                    ->each(function ($productImage, $index) use ($product) {
                        $image = ProductImage::find(data_get($productImage, 'id'));

                        if ($image) {
                            $data = [
                                'sort_number' => $index + 1,
                                'image_path' => $image->getRawOriginal('image_path'),
                                'thumbnail_image_path' => $image->getRawOriginal('thumbnail_image_path'),
                                'video_link' => $image->getRawOriginal('video_link'),
                                'type' => $image->getRawOriginal('type'),
                            ];
                        } else {
                            $data = [
                                'sort_number' => $index + 1,
                                'image_path' => data_get($productImage, 'attributes.image_path'),
                                'thumbnail_image_path' => data_get($productImage, 'attributes.thumbnail_image_path'),
                                'video_link' => data_get($productImage, 'attributes.video_link'),
                                'type' => data_get($productImage, 'attributes.type')
                            ];
                        }

                        $productImage = $product->images()->make()->forceFill($data);

                        $productImage->save();
                    });

                $product->load('images');
            }

            if ($request->has('data.relationships.description_items.data')) {
                $product->syncDescriptionItems(
                    data_get($request, 'data.relationships.description_items.data')
                );

                $product->load('items');
            }

            if ($request->has('data.relationships.product_properties.data')) {
                $product->syncProductProperties(
                    data_get($request, 'data.relationships.product_properties.data')
                );

                $product->load('properties');
            }

            if ($request->filled('data.relationships.product_recurrences.data')) {
                collect($request->input('data.relationships.product_recurrences.data'))
                    ->each(function ($recurrence) use ($product) {
                        $recurrence = $product
                            ->recurrences()
                            ->where('code', data_get($recurrence, 'attributes.code'))
                            ->first();

                        $recurrence->forceFill([
                            'is_discount_label_enabled' => data_get($recurrence, 'attributes.is_discount_label_enabled'),
                            'subheader' => data_get($recurrence, 'attributes.subheader')
                        ]);

                        $recurrence->save();
                    });
            }

            $service->createNewVariants(clone $product);

            return new CreatedResource($product->refresh());
        });
    }

    /**
     * Generate a slug based on title
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function generateSlug(Request $request)
    {
        $request->validate([
            'data.attributes' => 'required',
            'data.attributes.title' => 'required'
        ]);

        return response()->json(['slug' => $this->setSlug($request->input('data.attributes.title'))]);
    }


    /**
     * Reset total sold count
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    protected function resetSales(Request $request, $product)
    {
        $product = Product::find($product);

        if (!$product) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        $product->forceFill(['total_sold' => 0]);
        $product->save();

        $variant = v2ProductVariant::where('product_id', $product->id)->first();

        if ($variant) {
            $variant->forceFill(['sold' => 0]);
            $variant->save();
        }

        return response()->json([], 204);
    }

    /**
     * Generate a deep link
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function generateDeepLink(Request $request)
    {
        $request->validate([
            'data.attributes' => 'required',
            'data.attributes.slug' => 'required',
            'data.attributes.merchant_id' => [
                'required',
                Rule::exists('merchants', 'id')
                    ->where('is_enabled', true)
                    ->whereNotNull('verified_at')
                    ->withoutTrashed(),
            ],
        ]);

        $merchant = Merchant::find($request->input('data.attributes.merchant_id'));

        $scheme = app()->isLocal() ? 'http' : 'https';
        $slug = $request->input('data.attributes.slug');
        $summaryUrl = config('bukopay.url.deep_link_summary');

        $deepLink = ProductDeepLink::createShortUrl("{$scheme}://{$merchant->subdomain}.{$summaryUrl}?product={$slug}");

        return response()->json(['deep_link' => $deepLink]);
    }

    /**
     * Set the slug
     *
     * @param  $title
     *
     * @return string
     */
    protected function setSlug($title)
    {
        $slug = Str::slug($title, '-');

        $checkDuplicate = Product::where('slug', $slug)->first();

        if ($checkDuplicate) {
            $slug = $this->reNameSlug($slug);
        }

        return $slug;
    }

      /**
     * Rename the duplicated slug.
     *
     * @param  string  $slug
     * @param  int  $count
     *
     * @return string
     */
    protected function reNameSlug($slug, $count = 0)
    {
        $mainSlug = $slug;

        if ($count === 0 ) {
            $count += 1;
        }

        $checkDuplicate = Product::firstWhere('slug', "{$mainSlug}-{$count}");

        if ($checkDuplicate) {
            $count++;
            return $this->reNameSlug($mainSlug, $count);
        }

        return $mainSlug."-{$count}";
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $product)
    {
        $product = QueryBuilder::for(Product::class)
            ->whereKey($product)
            ->when($request->isFromMerchant(), function ($query) use ($request) {
                $query->where('merchant_id', $request->userOrClient()->merchant_id);
            })
            ->apply()
            ->first();

        if (!$product) {
            throw (new ModelNotFoundException)->setModel(Product::class);
        }

        return new Resource($product);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  \App\Services\ProductService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Product $product, ProductService $service)
    {
        $this->authorizeRequest($request, $product);
        $this->validateRequest($request, $product);

        return DB::transaction(function () use ($request, $product, $service) {
            if ($request->from() === 'client') {
                return $this->updateAsClient($request, $product, $service);
            }

            if ($request->filled('data.relationships.product_groups.data')) {
                $product->syncProductGroups(
                    data_get($request, 'data.relationships.product_groups.data')
                );
            } else {
                $product->groups()->sync([]);
            }

            if ($request->hasOnly('product_groups','data.relationships')) {
                return new Resource($product->refresh());
            }

            if (
                !$request->hasOnly('relationships','data')
                && $request->filled('data.attributes')
            ) {
                $product->update($request->input('data.attributes'));
            }

            if ($request->has('data.relationships.recurrences.data')) {
                $service->syncRecurrences(
                    $product,
                    $request->input('data.relationships.recurrences.data') ?: []
                );
            }

            if ($request->has('data.relationships.options.data')) {
                $service->syncOptions(
                    $product,
                    $request->input('data.relationships.options.data') ?: []
                );
            }

            $service->syncVariants($product);
            $service->syncNewVariants($product);

            if (
                $request->filled('data.relationships.images.data')
                || $request->hasFile('data.relationships.images.data.*.attributes.image')
            ) {
                $images = $request->input('data.relationships.images.data', [])
                    + $request->file('data.relationships.images.data', []);

                $product->syncImages($images);
            } elseif (
                $request->has('data.relationships.images.data')
                && is_null($request->input('data.relationships.images.data'))
            ) {
                $product->images()->get()->each->delete();
            }

            if ($request->filled('data.relationships.description_items.data')) {
                $product->syncDescriptionItems(
                    data_get($request, 'data.relationships.description_items.data')
                );
            } else {
                $product->items()->get()->each->delete();
            }

            if ($request->filled('data.relationships.product_properties.data')) {
                $product->syncProductProperties(
                    data_get($request, 'data.relationships.product_properties.data')
                );
            } else {
                $product->properties()->get()->each->delete();
            }

            if ($request->filled('data.relationships.exclusive_product_groups.data')) {
                $product->syncExclusiveProductGroups(
                    data_get($request, 'data.relationships.exclusive_product_groups.data')
                );
            } else {
                $product->exclusiveGroups()->sync([]);
            }

            if ($request->filled('data.relationships.product_recurrences.data')) {
                collect($request->input('data.relationships.product_recurrences.data'))
                    ->each(function ($recurrence) use ($product) {
                        $recurrence = $product
                            ->recurrences()
                            ->where('code', data_get($recurrence, 'attributes.code'))
                            ->first();

                        $recurrence->forceFill([
                            'is_discount_label_enabled' => data_get($recurrence, 'attributes.is_discount_label_enabled'),
                            'subheader' => data_get($recurrence, 'attributes.subheader')
                        ]);

                        $recurrence->save();
                    });
            }

            return new Resource($product->refresh());
        });
    }

    /**
     * Authorize the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product|null  $product
     * @return void
     *
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    protected function authorizeRequest(Request $request, $product = null)
    {
        if (
            $request->isFromMerchant()
            && $product
            && $request->userOrClient()->merchant_id != $product->merchant_id
        ) {
            throw new UnauthorizedException;
        }
    }

    /**
     * Update the specified resource in storage as a client.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @param  \App\Services\ProductService  $service
     * @return \Illuminate\Http\JsonResponse
     */
    protected function updateAsClient($request, $product, $service)
    {
        if ($product->is_shopify_product) {
            throw new BadRequestException('Only non-Shopify products can be updated.');
        }

        $request->validate([
            'data.attributes.pricing_type' => [
                'required',
                Rule::in([Product::SIMPLE, Product::SPECIAL]),
            ],
            'data.attributes.base_price' => 'required|nullable|numeric|min:0',
            'data.attributes.subscription_price' => [
                'required',
                'nullable',
                'min:0',
                'max:99999999',
                'lte:data.attributes.base_price',
            ],
            'data.attributes.bimonthly_price' => [
                Rule::requiredIf(
                    $request->input('data.attributes.pricing_type') == Product::SPECIAL
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999'
            ],
            'data.attributes.quarterly_price' => [
                Rule::requiredIf(
                    $request->input('data.attributes.pricing_type') == Product::SPECIAL
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999'
            ],
            'data.attributes.semiannual_price' => [
                Rule::requiredIf(
                    $request->input('data.attributes.pricing_type') == Product::SPECIAL
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999'
            ],
            'data.attributes.annual_price' => [
                Rule::requiredIf(
                    $request->input('data.attributes.pricing_type') == Product::SPECIAL
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999'
            ],
        ],[
            'data.attributes.subscription_price.max' => 'Maximum allowed product price is 99,999,999',
            'data.attributes.bimonthly_price.max' => 'Maximum allowed product price is 99,999,999',
            'data.attributes.quarterly_price.max' => 'Maximum allowed product price is 99,999,999',
            'data.attributes.semiannual_price.max' => 'Maximum allowed product price is 99,999,999',
            'data.attributes.annual_price.max' => 'Maximum allowed product price is 99,999,999',
        ]);

        $product->update([
            'pricing_type' => $request->input('data.attributes.pricing_type'),
            'original_price' => $request->input('data.attributes.base_price'),
            'price' => $request->input('data.attributes.subscription_price'),
        ]);

        $product->syncRecurrenceOptions();

        $product->recurrences()->get()
            ->each(function (ProductRecurrence $recurrence) use ($request, $product) {
                switch ($recurrence->code) {
                    case 'weekly':
                    case 'semimonthly':
                    case 'monthly':
                        $recurrence->fill([
                            'original_price' => $product->original_price,
                            'price' => $product->price,
                        ]);

                        break;

                    case 'bimonthly':
                        $multiplier = 2;
                        $price = $request->input('data.attributes.bimonthly_price');

                    case 'quarterly':
                        $multiplier = 3;
                        $price = $request->input('data.attributes.quarterly_price');

                    case 'semiannual':
                        $multiplier = 6;
                        $price = $request->input('data.attributes.semiannual_price');

                    case 'annually':
                        $multiplier = isset($multiplier) ? $multiplier : 12;
                        $price = isset($price)
                            ? $price
                            : $request->input('data.attributes.annual_price');

                        if ($product->pricing_type == Product::SIMPLE) {
                            $recurrence->fill([
                                'original_price' => $product->original_price,
                                'price' => $product->price,
                            ]);
                        } else {
                            $recurrence->fill([
                                'original_price' => $product->original_price
                                    ? $product->original_price * $multiplier
                                    : null,
                                'price' => $price,
                            ]);
                        }

                        break;

                    case 'single':
                    default:
                        $recurrence->fill([
                            'original_price' => null,
                            'price' => $product->original_price,
                        ]);
                }

                $recurrence->save();
            });

        $service->cascadePrices($product);

        return new Resource($product->fresh('variants.optionValues'));
    }

    /**
     * Validate the request and return the detected merchant.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product|null  $product
     * @return \App\Models\Merchant
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest($request, $product = null)
    {
        if ($product || $request->hasOnly('relationships', 'data')) {
            return;
        }

        $request->validate([
            'data' => 'required',
            'data.attributes' => 'sometimes|required',
        ]);

        if ($request->isFromUser()) {
            $request->validate([
                'data.attributes.merchant_id' => [
                    'required',
                    Rule::exists('merchants', 'id')
                        ->withoutTrashed(),
                ],
            ]);

            $merchant = Merchant::find($request->input('data.attributes.merchant_id'));
        } else {
            $merchant = $request->userOrClient()->merchant;
        }

        $request->validate([
            'data.attributes.title' => 'required|string|max:255',
            'data.attributes.description' => 'nullable|string|max:65535',

            'data.attributes.original_price' => 'nullable|numeric|min:0|max:99999999',
            'data.attributes.price' => 'nullable|numeric|min:0|max:99999999',

            'data.attributes.are_multiple_orders_allowed' => 'required|boolean',
            'data.attributes.is_visible' => 'sometimes|boolean',
            'data.attributes.is_shippable' => 'sometimes|boolean',
            'data.attributes.meta_title' => 'sometimes|max:100',
            'data.attributes.meta_description' => 'sometimes|max:160',

            'data.relationships.images.data.*.attributes.image' => 'sometimes|image',

            'data.relationships.options.data.*.attributes.name' => 'sometimes|string|max:255',
            'data.relationships.options.data.*.attributes.values' => 'sometimes|array',

            'data.relationships.variants.data.*.attributes.original_price' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:99999999'
            ],
            'data.relationships.variants.data.*.attributes.price' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:99999999'
            ],

            'data.relationships.variants.data.*.attributes.stock_count' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:999999'
            ],
        ],
        [
            'data.attributes.original_price.max' => 'Maximum allowed product price is 99,999,999',
            'data.attributes.price.max' => 'Maximum allowed product price is 99,999,999',
            'data.relationships.variants.data.*.attributes.price.max' => 'Maximum allowed product price is 99,999,999',
            'data.relationships.variants.data.*.attributes.original_price.max' => 'Maximum allowed product price is 99,999,999',
        ]);

        return $merchant;
    }
}
