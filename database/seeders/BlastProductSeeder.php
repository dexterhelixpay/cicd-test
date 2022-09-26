<?php

namespace Database\Seeders;

use App\Models\MerchantEmailBlast;
use App\Models\MerchantProductGroup;
use App\Models\Product;
use Illuminate\Database\Seeder;

class BlastProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        MerchantEmailBlast::query()
            ->with('productGroup.products')
            ->has('productGroups')
            ->doesntHave('products')
            ->cursor()
            ->tapEach(function (MerchantEmailBlast $blast) {
                $products = $blast->productGroups
                    ->flatMap(function (MerchantProductGroup $group) {
                        return $group->products->map(function (Product $product) use ($group) {
                            return [
                                'id' => $product->getKey(),
                                'expires_at' => $group->pivot->{'grouped_merchant_blasts.expires_at'},
                            ];
                        });
                    })
                    ->unique('id')
                    ->mapWithKeys(function ($product) {
                        return [$product['id'] => ['expires_at' => $product['expires_at']]];
                    });

                $blast->products()->sync($products);
                // $blast->productGroups()->detach();
            })
            ->all();
    }
}
