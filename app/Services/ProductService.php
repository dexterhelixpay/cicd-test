<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Imports\ShippingFee;
use App\Models\Merchant;
use App\Models\MerchantRecurrence;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductRecurrence;
use App\Models\ProductVariant;
use App\Models\v2\ProductVariant as v2ProductVariant;
use App\Support\PaymentSchedule;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    /**
     * Cascade the product's recurrence pricing to variants.
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function cascadePrices(Product $product)
    {
        $product->recurrences->each(function (ProductRecurrence $recurrence) use ($product) {
            $product->variants()
                ->whereHas('optionValues', function ($query) use ($recurrence) {
                    $query
                        ->where('value', $recurrence->code)
                        ->whereRelation('option', 'code', 'recurrence');
                })
                ->update($recurrence->only(['original_price', 'price']));
        });
    }

    /**
     * Cascade the stock to the old variants.
     *
     * @param  \App\Models\v2\ProductVariant  $variant
     * @return \App\Models\v2\ProductVariant
     */
    public function cascadeStocks(v2ProductVariant $variant)
    {
        $variant->refresh()->load('optionValues', 'product.allVariants.optionValues');

        $variant->product->allVariants
            ->filter(function (ProductVariant $oldVariant) use ($variant) {
                if ($variant->optionValues->isEmpty()) {
                    return $oldVariant->is_default
                        || $oldVariant->optionValues->count() <= 1;
                }

                $matchedValues = $oldVariant->optionValues
                    ->whereIn('id', $variant->optionValues->modelKeys());

                return $matchedValues->count() === $variant->optionValues->count();
            })
            ->each(function (ProductVariant $oldVariant) use ($variant) {
                $oldVariant->updateQuietly(['stock_count' => $variant->stock]);
            });

        return $variant;
    }

    /**
     * Check if the given products still have stocks.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $products
     * @return void
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function checkStocks(Merchant $merchant, Arrayable|array $products)
    {
        $products = $this->normalizeData($products);
        $variants = ProductVariant::query()
            ->with(['optionValues.option', 'product.options.values'])
            ->whereRelation('product', 'merchant_id', $merchant->getKey())
            ->where(function ($query) use ($products) {
                $query
                    ->whereKey($products->pluck('product_variant_id')->filter())
                    ->orWhereIn('product_id', $products->pluck('product_id')->filter());
            })
            ->get();

        $invalidProducts = $products
            ->map(function ($product) use ($variants) {
                $product = $product->toArray();

                if (!Arr::hasAny($product, ['product_id', 'product_variant_id'])) {
                    return array_merge($product, [
                        'stock' => null,
                        'is_out_of_stock' => false,
                        'has_insufficient_stock' => false,
                    ]);
                }

                if ($variant = $variants->find(data_get($product, 'product_variant_id'))) {
                    return array_merge($product, [
                        'title' => $variant->product->title,
                        'product_option_values' => $variant->optionValues,
                        'stock' => $variant->stock_count,
                        'is_out_of_stock' => $variant->stock_count === 0,
                        'has_insufficient_stock' => $variant->stock_count > 0
                            && $variant->stock_count < ($product['quantity'] ?? 1),
                    ]);
                }

                $variant = $variants
                    ->firstWhere(function (ProductVariant $variant) use ($product) {
                        if ($product['product_id'] != $variant->product_id) {
                            return false;
                        }

                        if ($variant->product->options->count() > 2) {
                            return false;
                        }

                        $otherOption = $variant->product->options->firstWhere(
                            'code', '!=', 'recurrence'
                        );

                        if ($otherOption && $otherOption->values->count() > 1) {
                            return false;
                        }

                        return $variant->optionValues->contains(
                            'value', data_get($product, 'payment_schedule.frequency')
                        );
                    });


                if ($variant) {
                    return array_merge($product, [
                        'product_variant_id' => $variant->getKey(),
                        'title' => $variant->product->title,
                        'product_option_values' => $variant->optionValues,
                        'stock' => $variant->stock_count,
                        'is_out_of_stock' => $variant->stock_count === 0,
                        'has_insufficient_stock' => $variant->stock_count > 0
                            && $variant->stock_count < ($product['quantity'] ?? 1),
                    ]);
                }

                return null;
            })
            ->filter(function ($product) {
                if (!$product) return false;

                return ($product['is_out_of_stock'] ?? false)
                    || ($product['has_insufficient_stock'] ?? false);
            });

        if ($invalidProducts->isNotEmpty()) {
            throw new InsufficientStockException($invalidProducts);
        }
    }

    /**
     * Create new variants of the given product
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function createNewVariants(Product $product)
    {
        $product->loadMissing('options.values');

        $defaultVariant = $product->newVariants()
            ->firstOrNew(['is_default' => true])
            ->forceFill([
                'title' => $product->title,
                'is_default' => true,
                'is_visible' => $product->options->count() === 1,
            ]);

        $defaultVariant->save();

        $optionValues = $product->options
            ->where('code', '<>', 'recurrence')
            ->map(function (ProductOption $option) {
                return $option->values;
            });

        if ($optionValues->isEmpty()) {
            $this->cascadeStocks($defaultVariant);

            return $this->updateTotalStocks($product);
        }

        collect($optionValues->shift())
            ->crossJoin(...$optionValues->toArray())
            ->each(function ($optionValues) use ($product) {
                $optionValues = collect($optionValues);

                $variant = $product->newVariants()
                    ->whereHas('optionValues', function ($query) use ($optionValues) {
                        $query->whereKey($optionValues->pluck('id'));
                    }, '>=', $optionValues->count())
                    ->firstOrNew()
                    ->forceFill([
                        'title' => $product->title,
                        'variant_title' => $optionValues->implode('name', ' / '),
                        'is_default' => false,
                        'is_visible' => true,
                    ]);

                $variant->save();
                $variant->optionValues()->sync($optionValues->pluck('id'));

                $this->cascadeStocks($variant);
            });

        return $this->updateTotalStocks($product);
    }

    /**
     * Recreate the given variant on the new model.
     *
     * @param  \App\Models\ProductVariant  $variant
     * @return \App\Models\v2\ProductVariant
     */
    public function recreateVariant(ProductVariant $variant)
    {
        $variant->loadMissing('product', 'optionValues.option');

        $optionValues = $variant->optionValues
            ->filter(fn (ProductOptionValue $value) => $value->option->code !== 'recurrence');

        if ($optionValues->isEmpty()) {
            $newVariant = $variant->product->newVariants()->firstOrNew([
                'is_default' => true,
            ])->forceFill([
                'title' => $variant->product->title,
                'is_default' => true,
                'is_visible' => true,
            ]);

            return tap($newVariant)->save();
        }

        $newVariant = $variant->product->newVariants()
            ->whereHas('optionValues', function ($query) use ($variant) {
                $query->whereKey($variant->optionValues->modelKeys());
            }, '>=', $variant->optionValues->count())
            ->firstOrNew();

        $newVariant->forceFill([
            'title' => $variant->product->title,
            'variant_title' => $optionValues->implode('name', ' / '),
            'is_default' => false,
            'is_visible' => true,
        ]);

        return tap($newVariant, function ($newVariant) use ($optionValues) {
            $newVariant->save();
            $newVariant->optionValues()->sync($optionValues);
        });
    }

    /**
     * Restore stocks of the given products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $products
     * @return void
     */
    public function restoreStocks(Merchant $merchant, Arrayable|array $products)
    {
        $products = $this->normalizeData($products);
        $variants = ProductVariant::query()
            ->whereRelation('product', 'merchant_id', $merchant->getKey())
            ->whereKey($products->pluck('product_variant_id')->filter())
            ->get();

        $products->each(function ($product) use ($variants) {
            $variant = $variants->find(data_get($product, 'product_variant_id'));

            if ($variant) {
                $newVariant = $variant->getNewerEquivalent(function ($query) {
                    $query->sharedLock();
                });

                if (!is_null($newVariant->stock)) {
                    $newVariant
                        ->newQueryForRestoration($newVariant->getKey())
                        ->increment('stock', $product['quantity']);

                    $this->cascadeStocks($newVariant);
                    $this->updateTotalStocks($newVariant->product);
                }
            }
        });
    }

    /**
     * Sync the options/values to the given product.
     *
     * @param  \App\Models\Product  $product
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $options
     * @return \App\Models\Product
     */
    public function syncOptions(Product $product, Arrayable|array $options)
    {
        $product->loadMissing('recurrences');

        $options = $this->normalizeData($options)
            ->where('code', '<>', 'recurrence')
            ->merge([
                collect([
                    'code' => 'recurrence',
                    'name' => 'Frequency',
                    'values' => $product->recurrences->map(function ($recurrence) {
                        return collect([
                            'value' => $recurrence->code,
                            'name' => $recurrence->name,
                            'sort_number' => $recurrence->sort_number,
                        ]);
                    }),
                ])
            ])
            ->map(function ($data) use ($product) {
                $option = $product->options()
                    ->firstOrNew(
                        $data->only('code')->toArray(),
                        $data->only(['name', 'subtitle'])->toArray()
                    );

                $option->save();

                $values = collect($data['values'])
                    ->map(function ($value) use ($option) {
                        $value = $option->values()
                            ->firstOrNew(
                                $value->only('value')->toArray(),
                                $value->only(['name', 'sort_number'])->toArray()
                            );

                        return tap($value)->save();
                    });

                $option->values()
                    ->whereKeyNot($values->pluck('id'))
                    ->get()
                    ->each
                    ->delete();

                return $option;
            });

        $product->options()
            ->whereKeyNot($options->pluck('id'))
            ->get()
            ->each
            ->delete();

        return $product->load('options.values');
    }

    /**
     * Sync the recurrences of the given product.
     *
     * @param  \App\Models\Product  $product
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $recurrences
     * @return \App\Models\Product
     */
    public function syncRecurrences(Product $product, Arrayable|array $recurrences = [])
    {
        $product->loadMissing('merchant.recurrences');

        $recurrences = $this->normalizeData($recurrences);

        $product->merchant->recurrences
            ->each(function (MerchantRecurrence $recurrence) use ($product, $recurrences) {
                $productRecurrence = $product->recurrences()
                    ->firstOrNew(['code' => $recurrence->code], [
                        'is_visible' => $recurrence->is_enabled,
                    ])
                    ->fill([
                        'recurrence_id' => $recurrence->getKey(),
                        'name' => $recurrence->name,
                        'sort_number' => PaymentSchedule::getFrequencySortNumber($recurrence->code),
                    ]);

                if (!$recurrence->is_enabled) {
                    $productRecurrence->is_visible = false;
                }

                if ($foundRecurrence = $recurrences->firstWhere('code', $recurrence->code)) {
                    return $productRecurrence
                        ->fill(Arr::except($foundRecurrence->toArray(), [
                            'recurrence_id',
                            'name',
                            'code',
                            'sort_number',
                        ]))
                        ->save();
                }

                $variant = $product->variants()
                    ->whereHas('optionValues', function ($query) use ($recurrence) {
                        $query
                            ->where('value', $recurrence->code)
                            ->whereRelation('option', 'code', 'recurrence');
                    })
                    ->first();

                if ($variant) {
                    return $productRecurrence
                        ->fill($variant->only([
                            'subheader',

                            'original_price',
                            'price',

                            'initially_discounted_order_count',
                            'initially_discounted_price',
                            'initial_discount_percentage',
                            'initial_discount_label',

                            'is_discount_label_enabled',
                        ]))
                        ->save();
                }

                switch ($recurrence->code) {
                    case 'weekly':
                    case 'semimonthly':
                    case 'monthly':
                        $productRecurrence->fill([
                            'original_price' => $product->original_price,
                            'price' => $product->price,
                        ]);

                        break;

                    case 'bimonthly':
                    case 'quarterly':
                    case 'semiannual':
                    case 'annually':
                        if ($product->pricing_type == Product::SIMPLE) {
                            $productRecurrence->fill([
                                'original_price' => $product->original_price,
                                'price' => $product->price,
                            ]);
                        } else {
                            $price = ($product->original_price ?: 0)
                                * PaymentSchedule::getFrequencyMultiplier($recurrence->code);

                            $productRecurrence->fill([
                                'original_price' => $price ?: null,
                                'price' => $price ?: null,
                            ]);
                        }

                        break;

                    case 'single':
                    default:
                        $productRecurrence->fill([
                            'original_price' => null,
                            'price' => $product->original_price,
                        ]);
                }

                $productRecurrence->save();
            });

        return $product->load('recurrences');
    }

    /**
     * Sync the variants of the given product.
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function syncVariants(Product $product)
    {
        $product->loadMissing('merchant', 'options.values', 'recurrences');

        $optionValues = $product->options
            ->map(function (ProductOption $option) {
                return $option->values->map(function ($value) use ($option) {
                    return $value->setRelation('option', $option->withoutRelations());
                });
            });

        $variants = collect($optionValues->shift())
            ->crossJoin(...$optionValues->toArray())
            ->map(function ($optionValues) use ($product) {
                $optionValues = collect($optionValues);

                $recurrenceValue = $optionValues->firstWhere('option.code', 'recurrence');
                $productRecurrence = $product->recurrences->firstWhere('code', $recurrenceValue['value']);

                $variant = $product->variants()
                    ->whereHas('optionValues', function ($query) use ($optionValues) {
                        $query
                            ->whereIn('name', $optionValues->pluck('name'))
                            ->whereHas('option', function ($query) use ($optionValues) {
                                $query->whereIn('code', $optionValues->pluck('option.code'));
                            });
                    }, '=', $optionValues->count())
                    ->firstOrNew()
                    ->fill($productRecurrence->only([
                        'original_price',
                        'price',

                        'initially_discounted_order_count',
                        'initially_discounted_price',
                        'initial_discount_label',
                        'subheader',

                        'is_discount_label_enabled',
                    ]))
                    ->forceFill([
                        'title' => $optionValues->implode('name', ' / '),
                        'is_enabled' => $productRecurrence->is_visible,
                        'is_shippable' => $product->merchant->has_shippable_products,
                    ]);

                $variant->save();
                $variant->optionValues()->sync($optionValues->pluck('id'));

                return $variant;
            });

        $product->variants()->whereKeyNot($variants->pluck('id'))->get()->each->delete();

        return $product->load('variants');
    }

    /**
     * Sync the new variants of the given product.
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function syncNewVariants(Product $product)
    {
        $product->loadMissing('options.values');

        $oldVariants = $product->newVariants()->with('optionValues.option')->get();

        $optionValues = $product->options
            ->where('code', '<>', 'recurrence')
            ->map(function (ProductOption $option) {
                return $option->values->map(function ($value) use ($option) {
                    return $value->setRelation('option', $option->withoutRelations());
                });
            });

        $defaultVariant = $product->newVariants()
            ->firstOrNew(['is_default' => true])
            ->forceFill([
                'title' => $product->title,
                'is_default' => true,
                'is_visible' => $optionValues->isEmpty(),
            ]);

        $defaultVariant->save();

        $variants = collect($optionValues->shift())
            ->crossJoin(...$optionValues->toArray())
            ->map(function ($optionValues) use ($product, $oldVariants) {
                $optionValues = collect($optionValues);

                $variant = $product->newVariants()
                    ->whereHas('optionValues', function ($query) use ($optionValues) {
                        $query
                            ->whereIn('name', $optionValues->pluck('name'))
                            ->whereHas('option', function ($query) use ($optionValues) {
                                $query->whereIn('code', $optionValues->pluck('option.code'));
                            });
                    }, '=', $optionValues->count())
                    ->firstOrNew()
                    ->forceFill([
                        'title' => $product->title,
                        'variant_title' => $optionValues->implode('name', ' / '),
                        'is_default' => false,
                        'is_visible' => true,
                    ]);

                if (!$variant->exists) {
                    $firstOptionValue = $optionValues->first();

                    $minStock = $oldVariants
                        ->filter(function ($oldVariant) use ($firstOptionValue) {
                            $hasFirstOption = $oldVariant->optionValues
                                ->where('value', $firstOptionValue->value)
                                ->where('option.code', $firstOptionValue->option->code)
                                ->isNotEmpty();

                            return $hasFirstOption
                                && (is_null($oldVariant->stock) || $oldVariant->stock > 0);
                        })
                        ->min('stock');

                    $variant->stock = $minStock;
                }

                $variant->save();
                $variant->optionValues()->sync($optionValues->pluck('id'));

                return $variant;
            });

        $product->newVariants()
            ->where('is_default', false)
            ->whereKeyNot($variants->pluck('id'))
            ->get()
            ->each
            ->delete();

        return $product->load('newVariants');
    }

    /**
     * Take stocks from the given products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $products
     * @return void
     */
    public function takeStocks(Merchant $merchant, Arrayable|array $products)
    {
        $products = $this->normalizeData($products);
        $variants = ProductVariant::query()
            ->whereRelation('product', 'merchant_id', $merchant->getKey())
            ->whereKey($products->pluck('product_variant_id')->filter())
            ->get();

        $products->map(function ($product) use ($variants) {
            $variant = $variants->find(data_get($product, 'product_variant_id'));

            if ($variant) {
                $newVariant = $variant->getNewerEquivalent(function ($query) {
                    $query->sharedLock();
                });

                if (!is_null($newVariant->stock)) {
                    try {
                        $newVariant
                            ->newQueryForRestoration($newVariant->getKey())
                            ->decrement('stock', $product['quantity']);

                        return true;
                    } catch (QueryException $e) {
                        // If stock is insufficient or sold o      $newVariant = $variant->getNewerEquivalent();ut, re-check the stock.
                    } finally {
                        $this->cascadeStocks($newVariant);
                        $this->updateTotalStocks($newVariant->product);
                    }
                }
            }

            return true;
        })->tap(function (Collection $collection) use ($merchant, $products) {
            if ($collection->reject()->isNotEmpty()) {
                $this->checkStocks($merchant, $products);
            }
        });
    }

    /**
     * Increment sold count from the given products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $products
     * @return void
     */
    public function incrementSales(Merchant $merchant, Arrayable|array $products)
    {
        $products = $this->normalizeData($products);
        $variants = ProductVariant::query()
            ->whereRelation('product', 'merchant_id', $merchant->getKey())
            ->whereKey($products->pluck('product_variant_id')->filter())
            ->get();

        $products->each(function ($product) use ($variants) {
            $variant = $variants->find(data_get($product, 'product_variant_id'));

            if ($variant) {
                $newVariant = $variant->getNewerEquivalent(function ($query) {
                    $query->sharedLock();
                });

                $newVariant->sold += data_get($product, 'quantity', 0);
                $newVariant->save();
            }
        });
    }

    /**
     * Increment sold count from the given products.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Contracts\Support\Arrayable|array  $products
     * @return void
     */
    public function decrementSales(Merchant $merchant, Arrayable|array $products)
    {
        $products = $this->normalizeData($products);
        $variants = ProductVariant::query()
            ->whereRelation('product', 'merchant_id', $merchant->getKey())
            ->whereKey($products->pluck('product_variant_id')->filter())
            ->get();

        $products->each(function ($product) use ($variants) {
            $variant = $variants->find(data_get($product, 'product_variant_id'));

            if ($variant) {
                $newVariant = $variant->getNewerEquivalent(function ($query) {
                    $query->sharedLock();
                });

                $totalSold = $newVariant->sold - data_get($product, 'quantity', 0);

                $newVariant->sold = $totalSold > 0
                    ? $totalSold
                    : 0;

                $newVariant->save();
            }
        });
    }


    /**
     * Update the product's total stocks.
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function updateTotalStocks(Product $product)
    {
        $variants = $product->newVariants()
            ->where('is_visible', true)
            ->get();

        if ($variants->whereNull('stock')->isNotEmpty()) {
            $totalStocks = null;
        } else {
            $totalStocks = $variants->sum('stock');
        }

        return tap($product, function ($product) use ($totalStocks) {
            $product->setAttribute('total_stock', $totalStocks)->save();
        });
    }

     /**
     * Update the product's total sold.
     *
     * @param  \App\Models\Product  $product
     * @return \App\Models\Product
     */
    public function updateTotalSold(Product $product)
    {
        $variants = $product->newVariants()
            ->where('is_visible', true)
            ->get();

        $totalSold = $variants->sum('sold') ?? 0;

        return tap($product, function ($product) use ($totalSold) {
            $product->setAttribute('total_sold', $totalSold)->save();
        });
    }

    /**
     * Normalize JSON:API data.
     *
     * @param  mixed  $data
     * @return \Illuminate\Support\Collection
     */
    public function normalizeData($data)
    {
        if (!is_array($data) && !$data instanceof Arrayable) {
            return $data;
        }

        $data = collect($data);

        if ($data->has('data')) {
            return $this->normalizeData($data->get('data'));
        }

        if ($data->isList()) {
            return $data->map(fn ($data) => $this->normalizeData($data));
        }

        $newData = $data->has('attributes') ? $data->get('attributes') : $data;

        if ($data->has('relationships')) {
            collect($data->get('relationships'))
                ->each(function ($data, $relation) use (&$newData) {
                    $newData[$relation] = $this->normalizeData($data);
                });
        }

        return collect($newData)->map(fn ($data) => $this->normalizeData($data));
    }

    /**
     * Import the subscriptions for the given merchant.
     *
     * @param  \App\Models\Merchant  $merchant
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return \Illuminate\Support\Collection
     */
    public function importShippingFee(Merchant $merchant, UploadedFile $file)
    {
        ($import =  new ShippingFee($merchant))->import($file);

        $callback = function (Collection $shippingFee) use ($merchant) {
            $foundProduct = $merchant->products()->find($shippingFee->get('product_id'));
            $shippingMethod = $merchant->shippingMethods($shippingFee->get('shipping_method_id'));

            if (!$foundProduct || !$shippingMethod) return null;

            $shippingFee = $foundProduct->shippingFees()->updateOrCreate([
                    'shipping_method_id' =>  data_get($shippingFee, 'shipping_method_id')
                ]
                ,[
                    'is_enabled' => true,
                    'first_item_price' => data_get($shippingFee, 'first_item_price', 0),
                    'additional_quantity_price' => data_get($shippingFee, 'additional_quantity_price', 0),
                ]);
            $shippingFee->save();

            return $foundProduct->fresh('shippingFees');
        };

        return DB::transaction(function () use ($import, $callback) {
            return $import->shippingFees
                ->flatMap(function (Collection $shippingFees) use ($callback) {
                    return [$callback($shippingFees)];
                })->filter();
        });
    }
}
