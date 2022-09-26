<?php

namespace Database\Seeders;

use App\Models\DiscountType;
use Illuminate\Database\Seeder;

class DiscountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $types = [
            'Fixed Discount',
            'Percentage Discount',
        ];

        collect($types)->each(function ($name) {
            DiscountType::create(compact('name'));
        });
    }
}
