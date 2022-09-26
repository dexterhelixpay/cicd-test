<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantQuarterlyRecurrence_2021_09_03_110300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $recurrences = [
                [
                    'name' => 'Quarterly',
                    'code' => 'quarterly',
                    'is_enabled' => false,
                ]
            ];

            Merchant::query()
                ->cursor()
                ->tapEach(function ($merchant) use ($recurrences) {
                    $recurrences[0]['description'] = $merchant->has_shippable_products
                        ? 'Receive a new delivery every three months'
                        : 'Receive your order every three months';

                    $merchant->recurrences()->createMany($recurrences);
                })
                ->all();
        });
    }
}
