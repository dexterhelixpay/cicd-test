<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SetUserRoles_2021_09_27_115000 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superAdmins = User::whereIn('email', [
            'andrew@bukopay.ph',
            'jeff@bukopay.ph',
        ])->get();

        $admins = User::whereKeyNot($superAdmins->modelKeys())->get();

        DB::transaction(function () use ($superAdmins, $admins) {
            $superAdmins->each->assignRole('Super Admin');
            $admins->each->assignRole('Admin');
        });
    }
}
