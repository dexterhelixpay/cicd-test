<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class LoginLockoutSeeder extends Seeder
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
                'key' => 'CheckoutStorefrontLockOutPeriod',
                'value' => 5,
                'value_type' => 'integer',
            ],
            [
                'key' => 'CheckoutStorefrontMaxAttempts',
                'value' => 3,
                'value_type' => 'integer',
            ],
            [
                'key' => 'CPLockOutPeriod',
                'value' => 5,
                'value_type' => 'integer',
            ],
            [
                'key' => 'CPMaxAttempts',
                'value' => 3,
                'value_type' => 'integer',
            ],
            [
                'key' => 'ConsoleLockoutPeriod',
                'value' => 5,
                'value_type' => 'integer',
            ],
            [
                'key' => 'ConsoleMaxAttempts',
                'value' => 3,
                'value_type' => 'integer',
            ],
        ];

        collect($settings)->each(function ($setting) {
            Setting::make()
                ->forceFill(Arr::only($setting, 'value_type'))
                ->forceFill(Arr::except($setting, 'value_type'))
                ->save();
        });
    }
}
