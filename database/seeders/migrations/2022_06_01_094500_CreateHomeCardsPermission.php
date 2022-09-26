<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateHomeCardsPermission_2022_06_01_094500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            $this->createCPPermission();
        });
    }

    public function createCPPermission()
    {
        $cpPermission = [
            'value' => [
                'name' => 'CP: Manage Home Cards',
                'guard_name' => 'user',
                'action' => 'Manage Home Cards',
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
                    'CP: Manage Home Cards'
                ]);
            })
            ->all();
    }
}
