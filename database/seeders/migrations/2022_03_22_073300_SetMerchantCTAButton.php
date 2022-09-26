<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantCTAButton_2022_03_22_073300 extends Seeder
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
                ->whereNull('pay_button_text')
                ->cursor()
                ->tapEach(function (Merchant $merchant)  {
                    $merchant->update([
                        'pay_button_text' => 'Start Subscription'
                    ]);
                })
                ->all();
        });
    }
}
