<?php

use App\Models\MerchantRecurrence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetMerchantRecurrenceSingleToSingleOrder_2021_10_05_183500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            MerchantRecurrence::query()
                ->where('code', 'single')
                ->update(['name' => 'Single Order']);
        });
    }
}
