<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRecurrenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $service = new ProductService;

        Product::query()
            ->has('merchant.recurrences')
            // ->has('recurrences', '<', 8)
            ->chunk(50, function (Collection $products, $page) use ($service) {
                $this->command->getOutput()->info('Seeding page ' . $page . '.');

                DB::transaction(function () use ($products, $service) {
                    $this->command->withProgressBar(
                        $products,
                        function (Product $product) use ($service) {
                            $service->syncRecurrences($product);

                            $service->syncOptions(
                                $product,
                                $product->options()->with('values')->get()->toArray()
                            );

                            $service->syncVariants($product);
                            $service->syncNewVariants($product);
                        }
                    );
                });
            });
    }
}
