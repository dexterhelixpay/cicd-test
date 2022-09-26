<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateUsersPermission_2022_01_31_021400 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            User::query()
                ->whereHas('roles')
                ->cursor()
                ->tapEach(function(User $user) {
                    $user->givePermissionTo($user->roles->first()->getAllPermissions());
                })
                ->all();
        });

    }
}
