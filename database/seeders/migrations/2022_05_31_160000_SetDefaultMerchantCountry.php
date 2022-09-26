<?php

use App\Models\Country;
use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDefaultMerchantCountry_2022_05_31_160000 extends Seeder
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

            Merchant::query()
                ->where(function ($query) {
                    $query
                        ->where('country_id', 0)
                        ->orWhereNull('country_id');
                })
                ->whereNotNull('mobile_number')
                ->cursor()
                ->tapEach(function (Merchant $merchant) use ($philippines) {
                    $merchant->country_id = $philippines->getKey();

                    if (substr($merchant->mobile_number, 0, 2) == '09' && strlen($merchant->mobile_number) > 10) {
                        $merchant->mobile_number = substr($merchant->mobile_number, 1);
                        $merchant->formatted_mobile_number = "{$philippines->dial_code}{$merchant->mobile_number}";
                    }

                    $merchant->saveQuietly();
                })
                ->all();
        });
    }
}
