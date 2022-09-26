<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantRecurrences_2021_07_27_210500 extends Seeder
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
                    'name' => 'Weekly',
                    'code' => 'weekly',
                    'description' => 'Receive a new delivery once per week',
                    'is_enabled' => true,
                ],
                [
                    'name' => 'Every Other Week',
                    'code' => 'semimonthly',
                    'description' => 'Receive a new delivery once every two weeks',
                    'is_enabled' => true,
                ],
                [
                    'name' => 'Monthly',
                    'code' => 'monthly',
                    'description' => 'Receive a new delivery once per month',
                    'is_enabled' => true,
                ],
                [
                    'name' => 'Single',
                    'code' => 'single',
                    'description' => 'Receive a one time delivery',
                    'is_enabled' => true,
                ]
            ];

            Merchant::query()
                ->cursor()
                ->tapEach(function ($merchant) use ($recurrences) {
                    $merchant->recurrences()->createMany($recurrences);
                })
                ->all();
        });
    }
}
