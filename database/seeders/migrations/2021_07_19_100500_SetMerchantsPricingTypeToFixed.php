<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\PricingType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantsPricingTypeToFixed_2021_07_19_100500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::query()
                ->whereNull('pricing_type_id')
                ->whereNotNull('verified_at')
                ->update(['pricing_type_id' => PricingType::FIXED_PRICING]);
        });
    }
}
