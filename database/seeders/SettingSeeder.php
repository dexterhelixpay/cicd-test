<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class SettingSeeder extends Seeder
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
                'key' => 'IsDigitalWalletPaymentEnabled',
                'value' => 0,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'IsCcPaymentEnabled',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'IsBankTransferEnabled',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'OrderPriceEditTemplateLink',
                'value' => Storage::url('templates/OrderPriceEditTemplate.xlsx'),
            ],
            [
                'key' => 'PasswordMaxHistoryCount',
                'value' => 4,
                'value_type' => 'integer',
            ],
            [
                'key' => 'PasswordMaxAge',
                'value' => 90,
                'value_type' => 'integer',
            ],
            [
                'key' => 'PasswordDaysBeforeExpirationReminder',
                'value' => 3,
                'value_type' => 'integer',
            ],
            [
                'key' => 'PasswordMinLength',
                'value' => 8,
                'value_type' => 'integer',
            ],
            [
                'key' => 'PasswordRequireLetters',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'PasswordRequireMixedCase',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'PasswordRequireSymbols',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'PasswordRequireNumbers',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'AutoCancellationDays',
                'value' => 95,
                'value_type' => 'integer',
            ],
            [
                'key' => 'CachedNotificationKey',
                'value' => '',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoChargeRemindTime',
                'value' => '07:00',
                'value_type' => 'string',
            ],
            [
                'key' => 'DaysBeforeOrderReminder',
                'value' => 3,
                'value_type' => 'integer',
            ],
            [
                'key' => 'DaysAfterOrderReminder',
                'value' => 3,
                'value_type' => 'integer',
            ],
            [
                'key' => 'AutoEarlyRemindTime',
                'value' => '05:30',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoRemindTime',
                'value' => '06:00',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoLateRemindTime',
                'value' => '06:30',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoChargeTime',
                'value' => '07:00',
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoCancelTime',
                'value' => '07:30',
                'value_type' => 'string',
            ],
            [
                'key' => 'MaxMerchantApiKeys',
                'value' => '3',
                'value_type' => 'integer',
            ],
            [
                'key' => 'IsDiscordSettingEnabled',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'IsDiscordEmailInviteEnabled',
                'value' => 1,
                'value_type' => 'boolean',
            ],
            [
                'key' => 'HomePageHeadline',
                'value' => 'Welcome {merchantName}',
                'value_type' => 'string',
            ],
            [
                'key' => 'HomePageSubtitle',
                'value' => join(' ', [
                    'This is your HelixPay Console.',
                    'You can build and grow your subscription business from here.',
                    "\nWe will also post announcements about new features and any important updates here as well."
                ]),
                'value_type' => 'string',
            ],
            [
                'key' => 'AutoFailLapsedPaymentMinutes',
                'value' => '10',
                'value_type' => 'integer',
            ]
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
