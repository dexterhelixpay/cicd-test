<?php

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetProductSlug_2022_05_14_002000 extends Seeder
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
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Product $product) {
                    $product->slug = Str::slug($product->title);
                    $product->saveQuietly();
                })->all();
        });
    }
}
