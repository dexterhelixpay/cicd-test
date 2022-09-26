<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantProductsDetailsEnabled_2022_06_02_203100 extends Seeder
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
                ->whereNull('deleted_at')
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $merchant->update([
                        'is_product_details_enabled' =>true
                    ]);
                })
                ->all();
        });
    }
}
