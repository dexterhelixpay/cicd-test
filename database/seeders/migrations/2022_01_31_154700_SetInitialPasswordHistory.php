<?php

use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Database\Seeder;

class SetInitialPasswordHistory_2022_01_31_154700 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::whereNotNull('password')->get()->each(function (User $user) {
            $user->logPasswordUpdate(true);
        });

        MerchantUser::whereNotNull('password')->get()->each(function (MerchantUser $user) {
            $user->logPasswordUpdate(true);
        });
    }
}
