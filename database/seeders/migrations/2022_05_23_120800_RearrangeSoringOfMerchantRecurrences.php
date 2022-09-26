<?php

use App\Models\Merchant;
use App\Models\MerchantRecurrence;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RearrangeSoringOfMerchantRecurrences_2022_05_23_120800 extends Seeder
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
                ->cursor()
                ->tapEach(function (Merchant $merchant) {
                    $sorting = [
                        'single' => 1,
                        'weekly' => 2,
                        'semimonthly' => 3,
                        'monthly' => 4,
                        'bimonthly' => 5,
                        'quarterly' => 6,
                        'semiannual' => 7,
                        'annually' => 8
                    ];

                   $merchant
                        ->recurrences()
                        ->get()
                        ->each(function (MerchantRecurrence $recurrence) use($sorting) {
                            $recurrence->sort_number = $sorting[$recurrence->code];
                            $recurrence->saveQuietly();
                        });
                })
                ->all();
        });
    }
}
