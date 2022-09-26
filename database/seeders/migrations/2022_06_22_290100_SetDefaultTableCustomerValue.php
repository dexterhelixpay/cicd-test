<?php

use App\Models\TableColumn;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetDefaultTableCustomerValue_2022_06_22_290100 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            TableColumn::query()
                ->where('text', 'Customer')
                ->update(['value' => 'recipient']);

            TableColumn::query()
                ->where('value', 'shipping_address')
                ->update(['width' => '250px']);
        });
    }
}
