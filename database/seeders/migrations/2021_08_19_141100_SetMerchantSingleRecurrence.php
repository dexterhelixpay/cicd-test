<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantSingleRecurrence_2021_08_19_141100 extends Seeder
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
                    'name' => 'Single',
                    'code' => 'single',
                    'description' => 'Receive a one time delivery',
                    'is_enabled' => false,
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
