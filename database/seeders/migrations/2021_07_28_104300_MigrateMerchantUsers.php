<?php

use App\Models\Merchant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;

class MigrateMerchantUsers_2021_07_28_104300 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Merchant::all()->each(function (Merchant $merchant) {
                $user = $merchant->users()->create($merchant->only([
                    'username',
                    'email',
                    'mobile_number',
                    'password',
                    'name',
                    'is_enabled',
                ]));

                $user->assignRole('Owner')->markEmailAsVerified();
            });

            Client::query()
                ->whereNull('user_id')
                ->where('name', 'Merchants Password Grant Client')
                ->update([
                    'name' => 'Merchant Users Password Grant Client',
                    'provider' => 'merchant_users',
                ]);

            Client::query()
                ->whereNull('user_id')
                ->where('name', 'Merchants Personal Access Client')
                ->update([
                    'name' => 'Merchant Users Personal Access Client',
                    'provider' => 'merchant_users',
                ]);
        });

    }
}
