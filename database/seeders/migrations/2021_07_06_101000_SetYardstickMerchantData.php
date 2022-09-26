<?php

use App\Models\Merchant;
use App\Models\PricingType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetYardstickMerchantData_2021_07_06_101000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::create([
                'pricing_type_id' => PricingType::FIXED_PRICING,
                'username' => 'yardstick-test',
                'email' => 'yardstick-test@gmail.com',
                'password' => bcrypt('demo1234'),
                'name' => 'Yardstick-test',
                'subdomain' => app()->environment('production') 
                    ? 'yardstick-test'
                    : join('-', [app()->environment(), 'yardstick']),
                'description_title' => 'Description Title',

                'has_shippable_products' => true,
                'has_digital_products' => true,
                'is_enabled' => true,
                
                'verified_at' => now()->toDateTimeString(),
            ]);
        });
    }
}
