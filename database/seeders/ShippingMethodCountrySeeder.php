<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingMethodCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            ShippingMethod::query()
                ->get()
                ->each(function (ShippingMethod $shippingMethod) {
                    $philippines = Country::where('name', 'Philippines')->first();
                    $shippingMethod->countries()->sync($philippines);
                });
        });
    }
}
