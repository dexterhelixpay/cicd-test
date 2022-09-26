<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = [
            'Unpaid',
            'Paid',
            'Failed',
            'Skipped',
            'Cancelled',
            'Overdue'
        ];

        OrderStatus::truncate();

        collect($statuses)->each(function ($name) {
            OrderStatus::create(compact('name'));
        });
    }
}
