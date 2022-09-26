<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DbSeeder;

class SeederNameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DbSeeder::insert([
            ['name'=>'BankSeeder'],
            ['name'=>'ConvinenceTypeSeeder'],
            ['name'=>'CountrySeeder'],
            ['name'=>'DevelopmentUserSeeder'],
            ['name'=>'DiscountTypeSeeder'],
            ['name'=>'KycLinkSeeder'],
            ['name'=>'LoginLockoutSeeder'],
            ['name'=>'MerchantButtonsSeeder'],
            ['name'=>'MerchantSeeder'],
            ['name'=>'NewProductVariantSeeder'],
            ['name'=>'OrderNotificationSeeder'],
            ['name'=>'OrderNotificationSeeder.xlsx'],
            ['name'=>'OrderStatusSeeder'],
            ['name'=>'PaymayaMidSeeder'],
            ['name'=>'PaymayaWalletSeeder'],
            ['name'=>'PaymentErrorResponseSeeder'],
            ['name'=>'PaymentStatusSeeder'],
            ['name'=>'PaymentTypeSeeder'],
            ['name'=>'PricingTypeSeeder'],
            ['name'=>'ProductRecurrenceSeeder'],
            ['name'=>'ProvinceSeeder'],
            ['name'=>'RoleSeeder'],
            ['name'=>'SetAutoChargeStartAndEndSettings'],
            ['name'=>'SetConsoleDocumentationSettings'],
            ['name'=>'SetDiscordSettings'],
            ['name'=>'SetHomePageSetting'],
            ['name'=>'SetIdleSeeder'],
            ['name'=>'SettingSeeder'],
            ['name'=>'ShippingMethodCountrySeeder'],
            ['name'=>'ShippingMethodSeeder'],
            ['name'=>'SocialLinkIconSeeder'],
            ['name'=>'SubscriptionSeeder'],
            ['name'=>'TableColumnSeeder'],
            ['name'=>'UserPermissionSeeder'],
            ['name'=>'UserSeeder']
        ]);
    }
}
