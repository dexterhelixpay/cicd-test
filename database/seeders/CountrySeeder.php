<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $countries = json_decode(file_get_contents(
            database_path('seeders/json/country.json')
        ), true);

        collect($countries)->each(function ($country) {
            Country::updateOrCreate(
                Arr::only($country, 'code'),
                Arr::except($country, 'code')
            );
        });
    }
}
