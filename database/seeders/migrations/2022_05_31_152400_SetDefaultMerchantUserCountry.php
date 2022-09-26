<?php

use App\Models\Country;
use App\Models\MerchantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDefaultMerchantUserCountry_2022_05_31_152400 extends Seeder
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

            MerchantUser::query()
                ->where(function ($query) {
                    $query
                        ->where('country_id', 0)
                        ->orWhereNull('country_id');
                })
                ->whereNotNull('mobile_number')
                ->cursor()
                ->tapEach(function (MerchantUser $merchantUser) use ($philippines) {
                    $merchantUser->country_id = $philippines->getKey();

                    if (substr($merchantUser->mobile_number, 0, 2) == '09' && strlen($merchantUser->mobile_number) > 10) {
                        $merchantUser->mobile_number = substr($merchantUser->mobile_number, 1);
                        $merchantUser->formatted_mobile_number = "{$philippines->dial_code}{$merchantUser->mobile_number}";
                    }

                    $merchantUser->saveQuietly();
                })
                ->all();
        });
    }
}
