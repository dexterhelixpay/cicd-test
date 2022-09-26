<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateDiscordPermission_2022_06_23_032300 extends Seeder
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
                'name' => 'CP: Merchants - Manage Discord Settings',
                'guard_name' => 'user',
                'action' => 'Manage Discord Settings',
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
            ->tapEach(function(User $user) use ($savedPermission){
                $user->givePermissionTo($savedPermission);
            })
            ->all();
    }
}
