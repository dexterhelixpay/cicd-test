<?php

use App\Models\DiscountType;
use App\Models\ProductRecurrence;
use Illuminate\Database\Seeder;

class ConvertProductRecurrencePriceToDiscountValue_2021_10_26_145900 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ProductRecurrence::query()
            ->where('price', '>', 0)
            ->whereNotNull('price')
            ->cursor()
            ->tapEach(function($recurrence) {
                $originalPrice =  $recurrence->original_price;

                if (!$originalPrice =  $recurrence->original_price) {
                    return $recurrence->updateQuietly([
                        'discount_value' => null,
                        'discount_type_id' => null,
                    ]);
                }

                $discount = floor(
                    ($originalPrice - ($recurrence->price ?: 0)) / $originalPrice * 100
                );

                $recurrence->updateQuietly([
                    'discount_value' => $discount,
                    'discount_type_id' => DiscountType::PERCENTAGE
                ]);
            })
            ->all();
    }
}
