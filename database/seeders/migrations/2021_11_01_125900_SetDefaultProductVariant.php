<?php

use App\Models\MerchantRecurrence;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SetDefaultProductVariant_2021_11_01_125900 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Product::query()
                ->has('merchant.recurrences')
                ->cursor()
                ->tapEach(function (Product $product) {
                    if (!$product->original_price && $product->price) {
                        $product->updateQuietly(['original_price' => $product->price]);
                        $product->refresh();
                    }

                    $product
                        ->syncDefaultVariant()
                        ->syncRecurrenceOptions()
                        ->updateQuietly(['pricing_type' => Product::SIMPLE]);

                    if ($product->options()->where('code', '<>', 'recurrence')->doesntExist()) {
                        $recurrenceOption = $product->options()
                            ->where('code', 'recurrence')
                            ->first();

                        $product->merchant->recurrences
                            ->each(function (MerchantRecurrence $recurrence) use ($product, $recurrenceOption) {
                                $recurrenceValue = $recurrenceOption->values()
                                    ->where('value', $recurrence->code)
                                    ->first();

                                $variant = $product->variants()
                                    ->whereHas('optionValues', function ($query) use ($recurrenceValue) {
                                        $query->whereKey($recurrenceValue->getKey());
                                    })
                                    ->first();

                                $originalPrice = $product->original_price;
                                $price = $product->price;

                                if ($recurrence->code === 'single') {
                                    $originalPrice = null;
                                    $price = $product->original_price;
                                }

                                if ($variant) {
                                    return $variant->updateQuietly([
                                        'original_price' => $originalPrice,
                                        'price' => $price,
                                    ]);
                                }

                                $variant = $product->variants()->create([
                                    'original_price' => $originalPrice,
                                    'price' => $price,

                                    'is_enabled' => $recurrence->is_enabled,
                                ]);

                                $variant->optionValues()->sync($recurrenceValue);
                            });

                        $product->syncRecurrencesFromVariants();
                    } else {
                        $product->allVariants()->get()->each(function (ProductVariant $variant) {
                            (new ProductVariantObserver)->updateTitle($variant);
                        });
                    }
                })
                ->all();

            // Product::query()
            //     ->whereNull('shopify_product_id')
            //     ->whereDoesntHave('options', function ($query) {
            //         $query->where('code', '<>', 'recurrence');
            //     })
            //     ->where(function ($query) {
            //         $recurrences = ['single', 'monthly', 'semimonthly', 'quarterly', 'annually'];

            //         foreach ($recurrences as $recurrence) {
            //             $query->orWhereHas('variants', function ($query) use ($recurrence) {
            //                 $query->whereHas('optionValues', function ($query) use ($recurrence) {
            //                     $query->where('value', $recurrence);
            //                 });
            //             }, '>', 1);
            //         }
            //     })
            //     ->cursor()
            //     ->tapEach(function (Product $product) {
            //         $product->variants()
            //             ->whereHas('optionValues.option', function ($query) {
            //                 $query->where('code', 'recurrence');
            //             })
            //             ->get()
            //             ->groupBy(function (ProductVariant $variant) {
            //                 return $variant->optionValues()
            //                     ->whereHas('option', function ($query) {
            //                         $query->where('code', 'recurrence');
            //                     })
            //                     ->first()
            //                     ->value;
            //             })
            //             ->each(function (Collection $variants) {
            //                 if ($variants->count() > 1) {
            //                     $variant = $variants->sortByDesc('created_at')->first();

            //                     $variant->optionValues()->detach();
            //                     $variant->delete();
            //                 }
            //             });
            //     })
            //     ->all();
        });
    }
}
