<?php

use App\Models\Province;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CreateDefaultShippingMethodGroups_2021_07_29_105500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shippingMethods = ShippingMethod::all();

        DB::transaction(function () use ($shippingMethods) {
            collect($shippingMethods)->each(function ($shippingMethod) {
                $metroManilaProvince = Province::firstWhere('name', 'Metro Manila');

                if ($shippingMethod->name === 'Metro Manila Delivery') {
                    $shippingMethod->provinces()->sync($metroManilaProvince);
                }
            });
        });
    }
}
