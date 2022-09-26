<?php

namespace App\Models;

use Exception;
use App\Casts\Html;
use Batch;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Ankurk91\Eloquent\Relations\BelongsToOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\v2\ProductVariant as v2ProductVariant;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Product extends RecordableModel
{
    use HasFactory, SoftDeletes;

    /**
     * Constant representing a simple pricing type.
     *
     * Single order: actual price = base price
     * Other recurrences: original price = base price, actual price = subscription price
     *
     * @var int
     */
    const SIMPLE = 1;

    /**
     * Constant representing a special pricing type.
     *
     * Single order: actual price = base price
     * Quarterly: original price = base price * 3, actual price = editable
     * Annual: original price = base price * 12, actual price = editable
     * Other recurrences: original price = base price, actual price = subscription price
     *
     * @var int
     */
    const SPECIAL = 2;

    /**
     * Constant representing a X / Y sold stock display
     *
     * @var integer
     */
    const STOCK_XY_SOLD_DISPLAY = 1;


    /**
     * Constant representing a X sold already! stock display
     *
     * @var integer
     */
    const STOCK_X_SOLD_DISPLAY = 2;

    /**
     * Constant representing a none stock display
     *
     * @var integer
     */
    const STOCK_NONE_DISPLAY = 3;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'merchant_id',
        'description',
        'details',
        'pricing_type',

        'shopify_info',
        'shopify_sku_id',

        'price',
        'original_price',

        'are_multiple_orders_allowed',
        'is_visible',
        'is_shopify_product',
        'is_shippable',
        'is_membership',
        'is_discord_invite_enabled',
        'is_deep_link_enabled',
        'deep_link',

        'sort_number',

        'deleted_at',
        'slug',
        'meta_title',
        'meta_description',
        'video_banner',

        'discord_channel',
        'discord_role_id',

        'product_stock_counter_type',
        'quantity_limit'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shopify_info' => 'array',
        'description' => Html::class,
        'details' => Html::class,
        'are_multiple_orders_allowed' => 'boolean',
        'is_visible' => 'boolean',
        'is_shopify_product' => 'boolean',
        'is_shippable' => 'boolean',
        'is_membership' => 'boolean',
        'is_discord_invite_enabled' => 'boolean',
        'is_deep_link_enabled' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['price_percentage'];

    /**
     * Get the label for the timeslot.
     *
     * @return string
     */
    public function getPricePercentageAttribute()
    {
        if (!$this->original_price) return null;

        return floor(100 - (($this->price / $this->original_price) * 100)) . '%';
    }

    /**
     * Get all posts restricted under this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function posts(): MorphToMany
    {
        return $this->morphToMany(Post::class, 'model', 'post_restrictions');
    }

    /**
     * Get all subscriptions subscribed to this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function subscriptions(): HasManyThrough
    {
        return $this->hasManyThrough(
            Subscription::class,
            SubscribedProduct::class,
            'product_id',
            'id',
            'id',
            'subscription_id',
        );
    }

    /**
     * Get all the product deep links.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function deepLinks(): HasMany
    {
        return $this->hasMany(ProductDeepLink::class);
    }

    /**
     * Get all the product teaser cards
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teaserCards(): HasMany
    {
        return $this->hasMany(ProductTeaserCard::class);
    }


    /**
     * Get all the product shipping fees.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function shippingFees(): HasMany
    {
        return $this->hasMany(ProductShippingFee::class);
    }

    /**
     * Get all the product variants.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function allVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the default product variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('is_default', true);
            });
    }

    /**
     * Get the recurrence
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(ProductRecurrence::class)
            ->orderBy('sort_number');
    }

    /**
     * Get the merchant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Get the groups that the product is under.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MerchantProductGroup::class, 'grouped_merchant_products');
    }

    /**
     * Get the groups that will enable the exclusive products.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function exclusiveGroups(): BelongsToMany
    {
        return $this->belongsToMany(MerchantProductGroup::class, 'exclusive_product_groups');
    }

    /**
     * Get the welcome email set for this product.
     *
     * @return \Ankurk91\Eloquent\Relations\BelongsToOne
     */
    public function welcomeEmail(): BelongsToOne
    {
        return $this->belongsToOne(WelcomeEmail::class);
    }

    /**
     * Get the product images.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_number');
    }

    /**
     * Get the product description items
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductDescriptionItem::class)->orderBy('sort_number');
    }

    /**
     * Get the new product variants.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function newVariants(): HasMany
    {
        return $this->hasMany(v2ProductVariant::class);
    }

    /**
     * Get the order records of this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orderedProducts(): HasMany
    {
        return $this->hasMany(OrderedProduct::class);
    }

    /**
     * Get the subscription records of this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscribedProducts(): HasMany
    {
        return $this->hasMany(SubscribedProduct::class);
    }

    /**
     * Get the product properties
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function properties(): HasMany
    {
        return $this->hasMany(ProductProperty::class)->orderBy('sort_number');
    }

    /**
     * Get the product options.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class);
    }

    /**
     * Get the product variants.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->where('is_default', false);
    }

    /**
     * Sync recurrence product options.
     *
     * @return self
     */
    public function syncRecurrenceOptions()
    {
        if (!$option = $this->options()->where('code', 'recurrence')->first()) {
            $option = $this->options()
                ->make([
                    'name' => 'Frequency',
                    'sort_number' => $this->options()->max('sort_number') + 1,
                ])
                ->setAttribute('code', 'recurrence');

            $option->save();
        }

        collect(optional($this->merchant)->recurrences ?? [])
            ->each(function (MerchantRecurrence $recurrence) use ($option) {
                if ($option->values()->where('value', $recurrence->code)->doesntExist()) {
                    $option->values()->create($recurrence->only('name') + [
                        'value' => $recurrence->code,
                        'sort_number' => $option->values()->max('sort_number') + 1,
                    ]);
                }
            });

        return $this->refresh();
    }

    /**
     * Sync recurrence and product options.
     *
     * @return self
     */
    public function syncProductOptions($data)
    {
        $options = data_get($data, 'attributes');
        $values = data_get($data, 'relationships.values.data');

        if (
            !$option = $this->options()
                ->where('code', data_get($options, 'code'))
                ->first()
        ) {
            $option = $this->options()
                ->make([
                    'name' => ucwords(data_get($options, 'name')),
                    'code' => data_get($options, 'code'),
                    'subtitle' => data_get($options, 'subtitle'),
                    'sort_number' => $this->options()->max('sort_number') + 1,
                ]);

            $option->save();
        }

        collect($values)
            ->each(function ($value) use ($option) {
                if ($option->values()->where('value', $value)->doesntExist()) {
                    $option->values()->create([
                        'name' => ucwords(data_get($value, 'attributes.name')),
                        'value' => data_get($value, 'attributes.value'),
                        'sort_number' => $option->values()->max('sort_number') + 1,
                    ]);
                }
            });

        return $this->refresh();
    }

    /**
     * Sync product info to the default variant.
     *
     * @return self
     */
    public function syncDefaultVariant()
    {
        if (!$variant = $this->defaultVariant()->first()) {
            $variant = $this->allVariants()->make()->forceFill(['is_default' => true]);
        }

        $variant
            ->fill($this->only('title', 'original_price', 'price') + [
                'is_enabled' => $this->is_visible,
            ])
            ->save();

        return $this;
    }

    /**
     * Sync the given product images.
     *
     * @param  array  $images
     * @return self
     */
    public function syncImages($images)
    {
        ksort($images);

        $imageIds = collect($images)->pluck('id')->filter()->all();
        $this->images()->whereKeyNot($imageIds)->get()->each->delete();

        collect($images)->each(function ($image, $index) {
            $productImage = $this->images()->findOrNew(data_get($image, 'id'));
            $productImage->fill(
                ['sort_number' => $index + 1]
            );

            if ($dataImage = data_get($image, 'attributes.image')) {
                $productImage->uploadImage($dataImage);
            }

            if ($attributes = data_get($image, 'attributes')) {
                $productImage->fill($attributes);
            }

            if ($imagePath = data_get($image, 'image_path')) {
                $productImage->image_path = $imagePath;
            } elseif ($imagePath = data_get($image, 'attributes.image_path')) {
                $productImage->image_path = $imagePath;
            }

            if (!$productImage->thumbnail_image_path) {
                $productImage->thumbnail_image_path = data_get($image, 'attributes.thumbnail_image_path');
            }

            $productImage->save();
        });

        return $this;
    }

    /**
     * Sync the product's recurrences from variants.
     *
     * @return self
     */
    public function syncRecurrencesFromVariants()
    {
        $hasRecurrenceOptions = $this->options()
            ->where('code', 'recurrence')
            ->exists();

        if (!$hasRecurrenceOptions) {
            return $this;
        }

        $this->variants()->get()->each(function (ProductVariant $variant, $index) {
            $recurrenceValue = $variant->optionValues()
                ->whereHas('option', function ($query) {
                    $query->where('code', 'recurrence');
                })
                ->first();

            if (!$recurrenceValue) return;

            $merchantRecurrence = $this->merchant->recurrences()
                ->where('code', $recurrenceValue->value)
                ->first();

            $this->recurrences()->updateOrCreate([
                'code' => $recurrenceValue->value
            ], array_merge($variant->only('price', 'original_price'), [
                'recurrence_id' => $merchantRecurrence->getKey(),

                'is_visible' => $variant->is_enabled,
                'sort_number' => (int) $index + 1,
            ]));
        });
    }

    /**
     * Sync the given product recurrences items
     *
     * @param array $items
     * @return self
     */
    public function syncRecurrences($items)
    {
        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->recurrences()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            $productRecurrence = $this->recurrences()->findOrNew(data_get($item, 'id'));
            $productRecurrence->forceFill(data_get($item, 'attributes'));
            $productRecurrence->sort_number = (int) $index + 1;

            $productRecurrence->save();
        });

        return $this->refresh();
    }

    /**
     * Sync the shopify images
     *
     * @param array $images
     * @param book $isWebhookUpdate
     * @return self
     */
    public function syncShopifyImages($images, $isWebhookUpdate = false)
    {
        // TODO: Optimize query
        $this->images()->get()->each->delete();

        collect($images)
            ->pluck($isWebhookUpdate ? 'src' : 'url')
            ->each(function ($image, $index) {
                $productImage = $this->images()
                    ->make()
                    ->forceFill([
                        'image_path' => $image,
                        'thumbnail_image_path' => $image
                    ]);

                $productImage->sort_number = (int) $index + 1;
                $productImage->save();
            });

        return $this;
    }

    /**
     * Sync the shopify options
     *
     * @param array $options
     * @return self
     */
    public function syncOptions($options)
    {
        // TODO: Optimize query
        $this->options()->get()->each(function ($option) {
            $option->values()->get()->each->delete();
            $option->delete();
        });

        collect($options)
            ->each(function ($shopifyOption, $index) {
                $code = $shopifyOption['name'] == 'Frequency'
                    || $shopifyOption['name'] == 'Recurrence'
                    ? 'recurrence'
                    : Str::snake($shopifyOption['name']);

                if (Arr::has($shopifyOption, 'code')) {
                    $code = $shopifyOption['code'];
                }

                $option = $this->options()->make([
                    'name' => $shopifyOption['name'],
                    'code' => $code,
                    'sort_number' => (int) $index + 1
                ]);
                $option->save();

                $option->values()->createMany(
                    collect($shopifyOption['values'])
                        ->map(function ($value, $index) {
                            return [
                                'name' => is_array($value) ? $value['name'] : $value,
                                'value' => is_array($value) ? $value['value'] : Str::snake($value),
                                'sort_number' => (int) $index + 1
                            ];
                        })
                );
            });

        return $this;
    }

    public function syncShopifyGroups($collections)
    {
        collect($collections)
            ->each(function ($collection, $index) {
                $productGroup = MerchantProductGroup::updateOrCreate(
                    [
                        'name' => $collection['title'],
                        'shopify_collection_id' => $collection['legacyResourceId'],
                        'merchant_id' => $this->merchant->id
                    ],
                    [
                        'shopify_info' => $collection,
                        'is_shopify_group' => true,
                        'is_visible' => true
                    ]
                );

                if ($productGroup->wasRecentlyCreated) {
                    $productGroup->sort_number = $this
                        ->merchant
                        ->productGroups()
                        ->count();

                    $productGroup->save();
                }

                $product = $productGroup->products()
                    ->where('product_id', $this->id)
                    ->exists();

                if (!$product) {
                    $productGroup->products()->attach($this->id, [
                        'sort_number' => $productGroup->products()->count() + 1
                    ]);
                }
            });
    }

    /**
     * Sync the shopify metafields to product recurrences
     *
     * @param array $recurrences
     *
     * @return self
     */
    public function syncShopifyProductRecurrences($recurrences)
    {
        // TODO: Optimize query
        $this->recurrences()->whereNotIn(
            'code',
            collect($recurrences)->pluck('code')->filter()->all()
        )->get()->each->delete();

        collect($recurrences)
            ->each(function ($shopifyRecurrence, $index) {
                $recurrence = $this->merchant->recurrences()
                    ->where('code', $shopifyRecurrence['code'])
                    ->first();

                ProductRecurrence::withoutEvents(function () use ($recurrence, $shopifyRecurrence, $index) {
                    $this->recurrences()->updateOrCreate([
                        'code' => $shopifyRecurrence['code'],
                    ], [
                        'recurrence_id' => $recurrence->id,
                        'code' => $shopifyRecurrence['code'],
                        'discount_type_id' => Arr::has($shopifyRecurrence, 'discountType')
                            ? $shopifyRecurrence['discountType']
                            : null,
                        'computation_type_id' => $shopifyRecurrence['computationType']
                            ?? ProductRecurrence::SIMPLE,
                        'discount_value' => Arr::has($shopifyRecurrence, 'discountValue')
                            ? $shopifyRecurrence['discountValue']
                            : null,
                        'sort_number' => (int) $index + 1,
                        'is_visible' => $recurrence->is_enabled,
                    ]);
                });
            });

        return $this;
    }

    /**
     * Sync the shopify variants
     *
     * @param array $variants
     * @param bool $isWebhookUpdate
     * @return self
     */
    public function syncShopifyVariants($variants, $isWebhookUpdate = false, $shopifyProductId)
    {
        // TODO: Optimize query
        $this->variants()->get()->each(function ($variant) {
            $variant->optionValues()->get()->each->delete();
            $variant->delete();
        });

        $this->syncDefaultVariant();

        $recurrences = $this->recurrences;
        $recurrences->load('recurrence');

        collect($variants)
            ->each(function ($shopifyVariant) use (
                $recurrences,
                $isWebhookUpdate,
                $shopifyProductId
            ) {
                collect($recurrences)
                    ->each(function ($productRecurrence) use (
                        $shopifyVariant,
                        $isWebhookUpdate,
                        $shopifyProductId
                    ) {
                        $recurrence = $productRecurrence->recurrence;
                        $price = $originalPrice = $productRecurrence->getPrice($shopifyVariant['price']);

                        $discountedPrice = null;
                        $shopifyVariantId = $isWebhookUpdate
                            ? $shopifyVariant['id']
                            : $shopifyVariant['legacyResourceId'];

                        if ($productRecurrence->discount_value) {
                            if ($productRecurrence->discount_type_id == ProductRecurrence::PERCENTAGE) {
                                $discountedPrice = $productRecurrence->computeShopifyDiscount($shopifyVariant['price']);
                                $price -= $discountedPrice;
                            }
                            // TODO: Handling for fixed discount
                        }

                        if ($isWebhookUpdate) {
                            $shopifyVariant['compareAtPrice'] = $shopifyVariant['compare_at_price'];
                        }

                        $variant = $this->variants()->make([
                            'title' => $shopifyVariant['title'] == 'Default Title'
                                ? $recurrence['name']
                                : "{$shopifyVariant['title']} / {$recurrence['name']}",
                            'shopify_variant_id' => $shopifyVariantId,
                            'sku' => $shopifyVariant['sku'] ?? null,
                            'price' => ($discountedPrice || $productRecurrence->computation_type_id == ProductRecurrence::WEEKLY_PRICING)
                                ? ($this->merchant->is_special_rounding ? sort_of_round($price) : $price)
                                : $shopifyVariant['price'],
                            'original_price' => $discountedPrice ? $originalPrice : null,
                            'is_shippable' => $isWebhookUpdate
                                ? $shopifyVariant['requires_shipping']
                                : $shopifyVariant['inventoryItem']['requiresShipping'],
                            'is_enabled' => true
                        ]);
                        $variant->save();

                        $options = $isWebhookUpdate
                            ? array_values(Arr::only($shopifyVariant, ['option1', 'option2', 'option3']))
                            :  collect($shopifyVariant['selectedOptions'])->pluck('value')->toArray();

                        $optionValues = array_merge(
                            $options,
                            [$recurrence['name']]
                        );

                        ProductOptionValue::query()
                            ->whereIn('name', $optionValues)
                            ->whereHas('option', function ($query) {
                                $query->where('product_id', $this->id);
                            })
                            ->cursor()
                            ->tapEach(function (ProductOptionValue $optionValue) use (&$variant) {
                                $variant->optionValues()->attach($optionValue->id);
                            })
                            ->all();

                        $this->syncShopifyOrderedProducts(
                            $variant->fresh(),
                            $options,
                            $shopifyVariantId,
                            $shopifyProductId,
                            $recurrence['code']
                        );
                    });
            });

        return $this;
    }

  /**
     * Update Ordered Products
     *
     * @return void
     */
    public function syncShopifyOrderedProducts(
        $variant,
        $optionValues,
        $shopifyVariantId,
        $shopifyProductId,
        $recurrenceCode
    ) {
        $orderedProductData = [];
        $subscribedProductData = [];
        $orderIdsToRecompute = [];

        OrderedProduct::query()
            ->where('product_id', $this->id)
            ->where('payment_schedule->frequency', $recurrenceCode)
            ->whereHas('order.subscription', function ($query) {
                $query->whereNull('completed_at')->whereNull('cancelled_at');
            })
            ->whereNotNull('shopify_product_info')
            ->where(function ($query) use ($shopifyVariantId, $shopifyProductId) {
                return $query->where(function ($query) use ($shopifyVariantId, $shopifyProductId) {
                    return $query->where('shopify_product_info->variant_id', $shopifyVariantId)
                        ->orWhere('shopify_product_info->id', $shopifyProductId);
                })
                    ->orWhere('shopify_product_info->legacyResourceId', $shopifyProductId);
            })
            ->cursor()
            ->tapEach(function ($orderedProduct) use (
                $variant,
                $optionValues,
                &$orderedProductData,
                &$subscribedProductData,
                &$orderIdsToRecompute
            ) {
                if ($orderedProduct->option_values) {
                    $orderedOptionValues = collect($orderedProduct->option_values)
                        ->filter(function ($optionValue, $key) {
                            return $key != 'Frequency';
                        })->map(function ($optionValues) {
                            return $optionValues;
                        })->values()->all();

                    if (array_diff($orderedOptionValues, $optionValues)) {
                        return;
                    }
                }

                $isPriceCascaded = $this->merchant->is_shopify_order_prices_editable;
                $subscribeProduct = $orderedProduct->subscribedProduct;

                if ($subscribeProduct) {
                    $subscribedProductPrice = $isPriceCascaded
                        ? $variant->price
                        : $orderedProduct->price;

                    array_push($subscribedProductData, [
                        'id' => $subscribeProduct->id,
                        'price' => $subscribedProductPrice,
                        'total_price' => $subscribedProductPrice
                            ? $subscribedProductPrice * $subscribeProduct->quantity
                            : null,
                        'product_variant_id' => $variant->id
                    ]);
                }

                $order = $orderedProduct->order;
                $isPending = $order && $order->payment_status_id != PaymentStatus::PAID;

                $orderedProductPrice = $isPriceCascaded && $isPending
                    ? $variant->price
                    : $orderedProduct->price;

                array_push($orderedProductData, [
                    'id' => $orderedProduct->id,
                    'price' => $orderedProductPrice,
                    'total_price' => $orderedProductPrice
                        ? $orderedProductPrice * $orderedProduct->quantity
                        : null,
                    'product_variant_id' => $variant->id
                ]);

                if (
                    $isPending
                    && $isPriceCascaded
                ) {
                    array_push($orderIdsToRecompute, $orderedProduct->order->id);
                }
            })
            ->all();


        if ($orderedProductData) {
            OrderedProduct::disableRecording();
            Batch::update(new OrderedProduct, $orderedProductData, 'id');
            OrderedProduct::enableRecording();
        }

        if ($subscribedProductData) {
            SubscribedProduct::disableRecording();
            Batch::update(new SubscribedProduct, $subscribedProductData, 'id');
            SubscribedProduct::enableRecording();
        }

        dispatch(function () use($orderIdsToRecompute) {
            Order::whereKey($orderIdsToRecompute)
                ->cursor()
                ->tapEach(function (Order $order) {
                    $order->setTotalPrice();
                    $order->subscription->setTotalPrice();
                })->all();
        })->afterResponse();

        return $this;
    }

    /**
     * Sync the given product descriptions.
     *
     * @param  array  $items
     * @return self
     */
    public function syncDescriptions($items)
    {
        $itemIds = collect($items)->pluck('id')->filter()->all();
        $this->items()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            ($itemModel = $this->items()->findOrNew(data_get($item, 'id')))
                ->fill(data_get($item, 'attributes'))
                ->setAttribute('sort_number', (int) $index + 1);

            if (Arr::has($item, 'attributes.bullet')) {
                $bullet = data_get($item, 'attributes.bullet');

                if ($bullet instanceof UploadedFile) {
                    $itemModel->uploadImage($bullet);
                } elseif (is_null($bullet)) {
                    $itemModel->bullet_icon_path = null;
                }
            }

            $itemModel->save();
        });

        return $this;
    }

    /**
     * Sync the given product description items
     *
     * @param array $items
     * @return self
     */
    public function syncDescriptionItems($items)
    {
        $itemIds = collect($items)->pluck('attributes.id')->filter()->all();
        $this->items()->whereKeyNot($itemIds)->get()->each->delete();

        collect($items)->each(function ($item, $index) {
            $productDescriptionItem = $this->items()->findOrNew(data_get($item, 'attributes.id'));
            $productDescriptionItem->fill([
                'sort_number' => (int) $index + 1,
                'text' => data_get($item, 'attributes.text')
            ]);

            $icon = data_get($item, 'attributes.bullet_icon');

            if (
                $icon
                && !is_string($icon)
            ) {
                $productDescriptionItem->uploadImage($icon);
            }

            if (is_null($icon)) {
                $productDescriptionItem->forceFill([
                    'bullet_icon_path' => null,
                ]);
            }

            $productDescriptionItem->save();
        });

        return $this;
    }

    /**
     * Sync the given product description items
     *
     * @param array $items
     * @return self
     */
    public function syncProductProperties($properties)
    {
        $propertyIds = collect($properties)->pluck('attributes.id')->filter()->all();
        $this->properties()->whereKeyNot($propertyIds)->get()->each->delete();

        collect($properties)->each(function ($property, $index) {
            $productProperty = $this->properties()->findOrNew(data_get($property, 'attributes.id'));

            $productProperty->fill(data_get($property, 'attributes'))
                ->setAttribute('sort_number', (int) $index + 1);

            $productProperty->save();
        });

        return $this;
    }

    /**
     * Sync the given product groups
     *
     * @param array $groups
     * @return self
     */
    public function syncProductGroups($groups)
    {
        $this->groups()->sync(
            collect($groups)
                ->mapWithKeys(function ($productGroup, $index) {
                    return [
                        $productGroup['id'] => ['sort_number' => (int) $index + 1]
                    ];
                })
                ->toArray()
        );
        return $this;
    }

    /**
     * Sync the given exclusive product groups
     *
     * @param array $groups
     * @return self
     */
    public function syncExclusiveProductGroups($groups)
    {
        $groupIds = $groups === 'any'
            ? MerchantProductGroup::where('merchant_id', $this->merchant_id)
            ->whereHas('products', function ($query) {
                $query->where('is_membership', true);
            })
            ->pluck('id')
            : collect($groups)->pluck('id');

        $this->exclusiveGroups()->sync($groupIds);

        return $this;
    }

    /**
     * Reset the product variants and options
     *
     * @return $this
     */
    public function variantsAndOptionsReset()
    {
        $this->variants()->delete();
        $this->newVariants()->get()->each->delete();

        $this->options()->get()->each(function ($option) {
            $option->values()->get()->each->delete();
            $option->delete();
        });

        return $this->refresh();
    }

    /**
     * Sync the variants.
     *
     * @param  array  $variants
     * @return $this
     */
    public function syncVariants($variants)
    {
        $this->variantsAndOptionsReset();

        collect($variants)
            ->each(function ($data) {
                $optionValues = collect(data_get($data, 'relationships.option_values.data'))
                    ->map(function ($data) {
                        if ($this->is_shopify_product && Arr::has($data, 'id')) {
                            return $data['id'];
                        }

                        $hasDefinedOption = Arr::has($data, [
                            'attributes.value',
                            'relationships.option.data.attributes.code'
                        ]);

                        if (!$hasDefinedOption) {
                            return null;
                        }

                        $this->syncProductOptions(collect($data));

                        $option = $this->options()
                            ->where('code', data_get($data, 'relationships.option.data.attributes.code'))
                            ->first();

                        return $option->values()
                            ->where('value', data_get($data, 'attributes.value'))
                            ->value('id');
                    })
                    ->filter()
                    ->all();

                $variant = $this->variants()
                    ->when(data_get($data, 'id'), function ($query, $variantId) {
                        $query->whereKey($variantId);
                    }, function ($query) use ($optionValues) {
                        $query->whereHas('optionValues', function ($query) use ($optionValues) {
                            $query->whereKey($optionValues);
                        }, '=', count($optionValues));
                    })
                    ->firstOrNew();

                $variant
                    ->fill($data['attributes'])
                    ->setAttribute('is_shippable', $this->merchant->has_shippable_products)
                    ->save();

                $variant->optionValues()->sync($optionValues);
            });

        return $this;
    }

    /**
     * Generate the variant combination from options.
     *
     * @param  array  $options
     * @return $this
     */
    public function generateVariantCombination($productOptions)
    {
        $existingOptions = $this->options
            ->whereNotIn('code', ['recurrence']);

        if (
            $this->options->isNotEmpty()
            && $productOptions->pluck('attributes.code') == $existingOptions->pluck('code')
            && $productOptions->pluck('relationships.*.data.*.attributes.code')
            ->flatten() ==
            $existingOptions->pluck('values')
            ->flatten()
            ->map(fn ($value) => $value->value)
            && (!$this->wasChanged('price') || !$this->wasChanged('original_price'))
        ) {
            $productOptions->each(function ($option) {
                $this->options()
                    ->where('code', data_get($option, 'attributes.code'))
                    ->first()
                    ->update(['subtitle' => data_get($option, 'attributes.subtitle')]);
            });

            return;
        }

        if (
            $this->recurrences
            ->crossJoin(...$productOptions->pluck('relationships.values.data'))
            ->count() > 100
        ) {
            throw new Exception('Exceeded maximum variants allowed');
        }

        $this->variantsAndOptionsReset();
        $this->syncRecurrenceOptions();

        $productOptions
            ->each(function ($options) {
                $this->syncProductOptions(collect($options));
            });

        $this->options->where('code', 'recurrence')
            ->pluck('values')
            ->flatten()
            ->each(function ($recurrence) {
                collect([$recurrence])
                    ->crossJoin(...$this->options->whereNotIn('code', ['recurrence'])->pluck('values'))
                    ->each(function ($value) use ($recurrence) {
                        $variant = $this->variants()
                            ->create(
                                Arr::except(
                                    $this->recurrences->where('code', $recurrence->value)->first()->toArray(),
                                    [
                                        'id',
                                        'created_at',
                                        'updated_at',
                                    ]
                                ) +
                                    [
                                        'title' => collect($value)->implode('name', ' / '),
                                        'is_shippable' => $this->merchant->has_shippable_products
                                    ]
                            );

                        $variant->optionValues()->sync(collect($value)->pluck('id'));
                    });
            });

        (new ProductService)->createNewVariants($this->fresh());

        return $this;
    }
}
