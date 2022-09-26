<?php

use App\Models\MerchantUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateFollowUpPermission_2022_03_31_104500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $this->createConsolePermission();
            $this->createCPPermission();
        });
    }

    public function createConsolePermission()
    {
        $permission = [
            'value' => [
                'name' => 'MC: Follow-up Emails',
                'guard_name' => 'merchant',
                'action' => 'Follow-up Emails',
            ],
        ];

        $savedConsolePermission = Permission::updateOrCreate(data_get($permission,'value'));

        $owner = Role::where('name','Owner')->first();
        $admin = Role::where('name','Admin')
            ->where('guard_name', 'merchant')
            ->first();

        $owner->givePermissionTo($savedConsolePermission);
        $admin->givePermissionTo($savedConsolePermission);

        MerchantUser::query()
            ->cursor()
            ->tapEach(function(MerchantUser $user) {
                $user->givePermissionTo([
                    'MC: Follow-up Emails',
                ]);
            })
            ->all();
    }


    public function createCPPermission()
    {
        $cpPermission = [
            'value' => [
                'name' => 'CP: Merchants - Manage Follow-up Emails',
                'guard_name' => 'user',
                'action' => 'Manage Follow-up Emails',
            ],
        ];

        $savedPermission = Permission::updateOrCreate(data_get($cpPermission, 'value'));

        $superAdmin = Role::where('name', 'Super Admin')->first();
        $superAdmin->givePermissionTo($savedPermission);

        $admin = Role::where('name', 'Admin')
            ->where('guard_name', 'user')
            ->first();

        $admin->givePermissionTo($savedPermission);

        $employee = Role::where('name', 'Employee')
            ->where('guard_name', 'user')
            ->first();

        $employee->givePermissionTo($savedPermission);

        User::query()
            ->whereHas('roles')
            ->cursor()
            ->tapEach(function(User $user) {
                $user->givePermissionTo([
                    'CP: Merchants - Manage Follow-up Emails'
                ]);
            })
            ->all();
    }
}
