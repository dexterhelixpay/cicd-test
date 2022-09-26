<?php

use App\Models\Customer;
use Illuminate\Database\Seeder;

class UpdateSandboxMobileNumber_2021_11_02_133600 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Customer::whereRaw('LENGTH(mobile_number) < 10')
            ->cursor()
            ->each(function ($customer) {
                $customer->mobile_number = $customer->mobile_number.rand(0,9);
                $customer->saveQuietly();
            });
    }
}
