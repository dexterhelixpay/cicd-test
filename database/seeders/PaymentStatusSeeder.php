<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $statuses = [
            'Not Initialized',
            'Pending',
            'Charged',
            'Paid',
            'Failed',
        ];

        collect($statuses)->each(function ($name) {
            PaymentStatus::create(compact('name'));
        });
    }
}
