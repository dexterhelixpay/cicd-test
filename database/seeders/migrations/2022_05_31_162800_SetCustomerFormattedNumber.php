<?php

use App\Models\Country;
use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetCustomerFormattedNumber_2022_05_31_162800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Customer::query()
                ->with('country')
                ->whereNotNull('mobile_number')
                ->cursor()
                ->tapEach(function (Customer $customer) {
                    $customer->formatted_mobile_number = $customer->country
                        ? "{$customer->country->dial_code}{$customer->mobile_number}"
                        : $customer->mobile_number;

                    $customer->saveQuietly();
                })
                ->all();
        });
    }
}
