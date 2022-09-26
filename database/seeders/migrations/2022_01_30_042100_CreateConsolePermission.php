<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateConsolePermission_2022_01_30_042100 extends Seeder
{

    public $permissions = [
        [
            'role' => ['admin','owner','staff'],
            'value' => [
                'name' => 'MC: Customers',
                'guard_name' => 'merchant',
                'action' => 'Customers',
            ],
        ],
        [
            'role' => ['admin','owner','staff'],
            'value' => [
                'name' => 'MC: Subscriptions',
                'guard_name' => 'merchant',
                'action' => 'Subscriptions',
            ],
        ],
        [
            'role' => ['admin','owner','staff'],
            'value' => [
                'name' => 'MC: Orders',
                'guard_name' => 'merchant',
                'action' => 'Orders',
            ],
        ],
        [
            'role' => ['admin','owner','staff'],
            'value' => [
                'name' => 'MC: Vouchers',
                'guard_name' => 'merchant',
                'action' => 'Vouchers',
            ],
        ],
        [
            'role' => ['admin','owner'],
            'value' => [
                'name' => 'MC: Finances',
                'guard_name' => 'merchant',
                'action' => 'Finances',
            ],
        ],
        [
            'role' => ['admin','owner','staff'],
            'value' => [
                'name' => 'MC: Products',
                'guard_name' => 'merchant',
                'action' => 'Products',
            ],
        ],
        [
            'role' => ['admin','owner'],
            'value' => [
                'name' => 'MC: Users',
                'guard_name' => 'merchant',
                'action' => 'Users',
            ],
        ],
        [
            'role' => ['owner','admin','staff'],
            'value' => [
                'name' => 'MC: Custom Fields',
                'guard_name' => 'merchant',
                'action' => 'Custom Fields',
            ],
        ],
        [
            'role' => ['admin','owner'],
            'value' => [
                'name' => 'MC: Settings',
                'guard_name' => 'merchant',
                'action' => 'Settings',
            ],
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::updateOrCreate([
            'name' => 'Staff',
            'guard_name' => 'merchant',
        ]);

        collect($this->permissions)
            ->each(function($permission) {
                $savedPermission = Permission::updateOrCreate(data_get($permission,'value'));

                $owner = Role::where('name','Owner')->first();
                $admin = Role::where('name','Admin')
                    ->where('guard_name', 'merchant')
                    ->first();

                $owner->givePermissionTo($savedPermission);
                $admin->givePermissionTo($savedPermission);

                if (in_array('staff', data_get($permission,'role'))) {
                    $staff = Role::where('name', 'Staff')
                        ->where('guard_name', 'merchant')
                        ->first();

                    $staff->givePermissionTo($savedPermission);
                }
            });
    }
}
