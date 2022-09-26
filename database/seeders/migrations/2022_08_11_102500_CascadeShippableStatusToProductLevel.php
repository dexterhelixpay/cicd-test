<?php

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantProductGroup;
use App\Models\Product;

class CascadeShippableStatusToProductLevel_2022_08_11_102500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Product::query()
            ->whereNull('deleted_at')
            ->with('merchant')
            ->cursor()
            ->tapEach(function (Product $product) {
                DB::transaction(function () use($product) {
                    $product->variants()->update($product->only('is_shippable'));
                });
            })->all();
    }
}
