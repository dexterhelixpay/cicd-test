<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SetAutoChargeStartAndEndSettings extends Seeder
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
                'key' => 'AutoChargeStartTime',
                'value' => '07:00',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoChargeEndtime',
                'value' => '11:00',
                'value_type' => 'string',
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
