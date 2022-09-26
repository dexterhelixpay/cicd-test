<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NewProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Product::query()
            ->with('variants.optionValues', 'options.values')
            ->withCount('variants')
            ->doesntHave('newVariants')
            ->orderBy('variants_count')
            ->cursor()
            ->tapEach(function (Product $product) {
                DB::transaction(function () use ($product) {
                    (new ProductService)->createNewVariants($product);
                });
            })
            ->all();
    }
}
