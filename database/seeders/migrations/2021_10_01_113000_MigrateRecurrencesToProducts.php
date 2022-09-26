<?php

use App\Models\MerchantRecurrence;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateRecurrencesToProducts_2021_10_01_113000 extends Seeder
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
                ->with('merchant.recurrences')
                ->has('merchant.recurrences')
                ->doesntHave('recurrences')
                ->cursor()
                ->tapEach(function (Product $product) {
                    $product->syncRecurrences(
                        $product->merchant->recurrences
                            ->map(function (MerchantRecurrence $recurrence) use ($product) {
                                if ($recurrence->code === 'single') {
                                    $originalPrice = null;
                                    $price = $product->original_price ?? $product->price;
                                } else {
                                    $originalPrice = $product->original_price;
                                    $price = $product->price;
                                }

                                return [
                                    'attributes' => [
                                        'recurrence_id' => $recurrence->getKey(),
                                        'code' => $recurrence->code,
                                        'price' => $price,
                                        'original_price' => $originalPrice,
                                        'is_visible' => true,
                                    ],
                                ];
                            })
                            ->toArray()
                    );
                })
                ->all();
        });
    }
}
