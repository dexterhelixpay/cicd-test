<?php

use App\Models\MerchantRecurrence;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetProductRecurrences_2022_04_29_090000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        ini_set("memory_limit", "-1");

        DB::transaction(function () {
            Product::query()
                ->has('merchant.recurrences')
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Product $product) {
                    $merchant = $product->merchant;

                    $option = $product->options()->where('code', 'recurrence')->first();

                    if ($option) {
                        collect($merchant->recurrences->whereIn('code', ['semiannual', 'bimonthly']) ?? [])
                            ->each(function (MerchantRecurrence $recurrence) use ($option, $product) {

                                if ($option->values()->where('value', $recurrence->code)->doesntExist()) {
                                    $option->values()->create($recurrence->only('name') + [
                                        'value' => $recurrence->code,
                                        'sort_number' => $option->values()->max('sort_number') + 1,
                                    ]);
                                }


                                $optionValueId =  $option->values()
                                    ->where('value', $recurrence->code)
                                    ->value('id');


                                $variant = $product->variants()
                                    ->whereHas('optionValues', function ($query) use ($optionValueId) {
                                        $query->whereKey($optionValueId);
                                    }, '=', 1)
                                    ->firstOrNew();

                                $variant->fill([
                                    'is_enabled' => false,
                                    'title' => $recurrence->name,
                                    'price' => null,
                                    'original_price' => null
                                ])->save();
                                $variant->optionValues()->sync([$optionValueId]);
                            });
                    }
                })
                ->all();
        });
    }
}
