<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantAnnualRecurrence_2021_10_22_053800 extends Seeder
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
                    'name' => 'Annually',
                    'code' => 'annually',
                    'is_enabled' => false,
                ]
            ];

            Merchant::query()
                ->cursor()
                ->tapEach(function ($merchant) use ($recurrences) {
                    $recurrences[0]['description'] = $merchant->has_shippable_products
                        ? 'Receive a new delivery every year'
                        : 'Receive your order every year';

                    $recurrences[0]['sort_number'] = $merchant->recurrences()->count() + 1;

                    $merchant->recurrences()->createMany($recurrences);
                })
                ->all();
        });
    }
}
