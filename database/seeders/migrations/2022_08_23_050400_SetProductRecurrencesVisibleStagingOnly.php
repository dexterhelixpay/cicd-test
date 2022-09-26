<?php

use App\Models\Product;
use Illuminate\Database\Seeder;
use App\Services\ProductService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SetProductRecurrencesVisibleStagingOnly_2022_08_23_050400 extends Seeder
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
            ->chunk(50, function (Collection $products, $page) use ($service) {
                $this->command->getOutput()->info('Seeding page ' . $page . '.');

                DB::transaction(function () use ($products, $service) {
                    $this->command->withProgressBar(
                        $products,
                        function (Product $product) use ($service) {
                            $service->syncRecurrences($product);
                        }
                    );
                });
            });
    }
}
