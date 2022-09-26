<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Database\Seeder;

class SetHomePageSetting extends Seeder
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
                'key' => 'HomePageHeadline',
                'value' => 'Welcome {merchantName}',
                'value_type' => 'string',
            ],
            [
                'key' => 'HomePageSubtitle',
                'value' => "This is your HelixPay Console. You can build and grow your subscription business from here. \n
                        We will also post announcements about new features and any important updates here as well.
                    ",
                'value_type' => 'string',
            ],
        ];

        collect($settings)->each(function ($setting) {
            if (Setting::where('key', $setting['key'])->doesntExist()) {
                Setting::make()
                    ->forceFill(Arr::only($setting, 'value_type'))
                    ->forceFill(Arr::except($setting, 'value_type'))
                    ->save();
            }
        });
    }
}
