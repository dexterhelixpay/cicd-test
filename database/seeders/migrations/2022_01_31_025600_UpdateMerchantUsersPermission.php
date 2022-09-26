<?php

use App\Models\MerchantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateMerchantUsersPermission_2022_01_31_025600 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            MerchantUser::query()
                ->cursor()
                ->tapEach(function(MerchantUser $user) {
                    $user->givePermissionTo($user->roles()->first()->permissions()->get());
                })
                ->all();
        });
    }
}
