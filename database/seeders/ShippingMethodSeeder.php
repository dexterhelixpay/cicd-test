<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!app()->isProduction()) {
            $methods = [
                [
                    'name' => 'Metro Manila Delivery',
                    'description' => 'The merchant will coordinate the shipping for each subscription delivery.',
                    'price' => 99,
                ],
                [
                    'name' => 'Province Delivery',
                    'description' => 'The merchant will coordinate the shipping for each subscription delivery.',
                    'price' => 199,
                ],
            ];

            Merchant::query()
                ->where('has_shippable_products', true)
                ->get()
                ->each(function (Merchant $merchant) use ($methods) {
                    $merchant->shippingMethods()->createMany($methods);
                });
        }
    }
}
