<?php

namespace Database\Seeders;

use App\Models\PricingType;
use Illuminate\Database\Seeder;

class PricingTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $types = [
            'Fixed Pricing',
            'Variable Pricing',
        ];

        collect($types)->each(function ($name) {
            PricingType::create(compact('name'));
        });
    }
}
