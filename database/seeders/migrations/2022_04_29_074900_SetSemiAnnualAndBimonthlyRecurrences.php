<?php

use App\Models\Merchant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetSemiAnnualAndBimonthlyRecurrences_2022_04_29_074900 extends Seeder
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
                    'name' => 'Semi Annual',
                    'code' => 'semiannual',
                    'sort_number' => 7,
                ],

                [
                    'name' => 'Every 2 Months',
                    'code' => 'bimonthly',
                    'sort_number' => 8,
                ]
            ];

            Merchant::query()
                ->cursor()
                ->tapEach(function ($merchant) use ($recurrences) {
                    $recurrences = collect($recurrences)
                        ->map(function ($recurrence) use($merchant) {
                            $months = $recurrence['code'] == 'bimonthly'
                                ? 'two'
                                : 'six';

                            $recurrence['description'] = $merchant->has_shippable_products
                                ? "Receive a new delivery every {$months} months"
                                : "Receive your order every {$months} months";

                            return $recurrence;
                        });

                    $merchant->recurrences()->createMany($recurrences);
                })
                ->all();
        });
    }
}
