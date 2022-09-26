<?php

use App\Models\Merchant;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetProductRecurrencePricing_2022_07_21_223500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $merchant->products
                        ->each(function(Product $product) use ($merchant) {
                            if ($product->shopify_info) return;

                            $product->recurrences
                                ->map(function($recurrence) use ($product) {
                                    $variant = $product->variants()
                                        ->whereHas('optionValues', function($query) use ($recurrence) {
                                            $query->where('value', $recurrence->code);
                                        })
                                        ->first();
                                    if (!$variant) return;

                                    $recurrence->update([
                                        'name' => $recurrence->recurrence->name,
                                        'price' => $variant->price,
                                        'original_price' => $variant->original_price,
                                        'initially_discounted_order_count' => $variant->initially_discounted_order_count,
                                        'initially_discounted_price' => $variant->initially_discounted_price,
                                        'initial_discount_percentage' => $variant->initial_discount_percentage,
                                        'initial_discount_label' => $variant->initial_discount_label,
                                        'subheader' => $variant->subheader,
                                        'is_discount_label_enabled' => $variant->is_discount_label_enabled,
                                    ]);
                                });
                        });

                })->all();
        });

    }
}
