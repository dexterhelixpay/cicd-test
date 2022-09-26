<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class SetDiscordSettings extends Seeder
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
                'key' => 'IsDiscordSettingEnabled',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'IsDiscordEmailInviteEnabled',
                'value' => 1,
                'value_type' => 'boolean',
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
