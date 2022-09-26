<?php

use App\Models\Country;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetCustomersCountry_2021_10_24_070000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $philippines = Country::where('code', 'PH')->first();

            Customer::query()
                ->where(function ($query) {
                    $query
                        ->where('country_id', 0)
                        ->orWhereNull('country_id');
                })
                ->whereNotNull('mobile_number')
                ->cursor()
                ->tapEach(function (Customer $customer) use ($philippines) {
                    $customer->country_id = $philippines->getKey();

                    if (strlen($customer->mobile_number) > 10) {
                        $customer->mobile_number = substr($customer->mobile_number, 1);
                    }

                    $customer->saveQuietly();
                })
                ->all();
        });
    }
}
