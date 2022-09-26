<?php

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class SetOverdueStatus_2022_04_28_105000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        OrderStatus::create(['name'=> 'Overdue']);
    }
}
