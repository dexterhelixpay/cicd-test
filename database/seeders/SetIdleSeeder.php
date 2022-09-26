<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SetIdleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            [
                'key' => 'IdleMaxLimit',
                'value' => 15,
                'value_type' => 'integer',
            ],
            [
                'key' => 'CPInActiveDays',
                'value' => 90,
                'value_type' => 'integer',
            ],
            [
                'key' => 'ConsoleInActiveDays',
                'value' => 90,
                'value_type' => 'integer',
            ]
        ];

        collect($settings)->each(function ($setting) {
            Setting::make()
                ->forceFill(Arr::only($setting, 'value_type'))
                ->forceFill(Arr::except($setting, 'value_type'))
                ->save();
        });
    }
}
