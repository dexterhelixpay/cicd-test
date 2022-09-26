<?php

namespace Database\Seeders;

use App\Models\ConvenienceType;
use Illuminate\Database\Seeder;

class ConvinenceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $types = [
            'Fixed Fee',
            'Percentage Fee',
        ];

        collect($types)->each(function ($name) {
            ConvenienceType::create(compact('name'));
        });
    }
}
