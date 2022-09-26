<?php

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateCPPermissions_2022_01_30_012000 extends Seeder
{

    public $permissions = [
        [
            'role' => ['admin','super admin','employee'],
            'value' => [
                'name' => 'CP: Merchants - View',
                'guard_name' => 'user',
                'action' => 'View',
            ],
        ],
        [
            'role' => ['admin','super admin','employee'],
            'value' => [
                'name' => 'CP: Merchants - Add',
                'guard_name' => 'user',
                'action' => 'Add',
            ],
        ],
        [
            'role' => ['admin','super admin','employee'],
            'value' => [
                'name' => 'CP: Merchants - Edit',
                'guard_name' => 'user',
                'action' => 'Edit',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: Merchants - Log in to Store',
                'guard_name' => 'user',
                'action' => 'Log in to Store',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: Merchants - Manage Finances',
                'guard_name' => 'user',
                'action' => 'Manage Finances',
            ],
        ],
        [
            'role' => ['admin','super admin','employee'],
            'value' => [
                'name' => 'CP: Merchants - Manage City Group',
                'guard_name' => 'user',
                'action' => 'Manage City Group',
            ],
        ],
        [
            'role' => ['admin','super admin','employee'],
            'value' => [
                'name' => 'CP: Merchants - Manage Custom Fields',
                'guard_name' => 'user',
                'action' => 'Manage Custom Fields',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: Merchants - Manage Shopify Settings',
                'guard_name' => 'user',
                'action' => 'Manage Shopify Settings',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Merchants - Manage PayMaya Keys',
                'guard_name' => 'user',
                'action' => 'Manage PayMaya Keys',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: User Management - View',
                'guard_name' => 'user',
                'action' => 'View',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: User Management - Add',
                'guard_name' => 'user',
                'action' => 'Add',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: User Management - Edit',
                'guard_name' => 'user',
                'action' => 'Edit',
            ],
        ],
        [
            'role' => ['admin','super admin'],
            'value' => [
                'name' => 'CP: User Management - Delete',
                'guard_name' => 'user',
                'action' => 'Delete',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Settings - Payment Method Settings',
                'guard_name' => 'user',
                'action' => 'Payment Method Settings',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Settings - Merchant Settings',
                'guard_name' => 'user',
                'action' => 'Merchant Settings',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Settings - View',
                'guard_name' => 'user',
                'action' => 'View',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Payment Settings - View',
                'guard_name' => 'user',
                'action' => 'View',
            ],
        ],
        [
            'role' => ['super admin'],
            'value' => [
                'name' => 'CP: Payment Settings - Manage Settings',
                'guard_name' => 'user',
                'action' => 'Manage Settings',
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
            'name' => 'Employee',
            'guard_name' => 'user',
        ]);

        collect($this->permissions)
            ->each(function($permission) {
                $savedPermission = Permission::updateOrCreate(data_get($permission, 'value'));

                $superAdmin = Role::where('name', 'Super Admin')->first();
                $superAdmin->givePermissionTo($savedPermission);

                if (in_array('admin', data_get($permission, 'role'))) {
                    $admin = Role::where('name', 'Admin')
                        ->where('guard_name', 'user')
                        ->first();

                    $admin->givePermissionTo($savedPermission);
                }

                if (in_array('employee', data_get($permission, 'role'))) {
                    $employee = Role::where('name', 'Employee')
                        ->where('guard_name', 'user')
                        ->first();

                    $employee->givePermissionTo($savedPermission);
                }
            });
    }
}
