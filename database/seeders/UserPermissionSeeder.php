<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'roles' => ['Super Admin'],
                'permission' => [
                    'name' => 'CP: Merchants - Setup Custom Domain',
                    'guard_name' => 'user',
                    'action' => 'Setup Custom Domain',
                ],
            ],

            [
                'roles' => ['Super Admin', 'Admin'],
                'permission' => [
                    'name' => 'CP: Merchants - Xendit',
                    'guard_name' => 'user',
                    'action' => 'Xendit',
                ],
            ]
        ];

        $roles = collect();

        collect($permissions)
            ->tap(function (Collection $permissions) use (&$roles) {
                $roles = $permissions
                    ->pluck('roles')
                    ->flatten()
                    ->unique()
                    ->map(function ($role) {
                        return Role::updateOrCreate([
                            'name' => $role,
                            'guard_name' => 'user',
                        ]);
                    })
                    ->keyBy('name');
            })
            ->each(function ($data) use ($roles) {
                $permission = Permission::updateOrCreate($data['permission']);

                collect($data['roles'])
                    ->each(function ($role) use ($permission, $roles) {
                        $roles->get($role)->givePermissionTo($permission);

                        if ($permission->wasRecentlyCreated) {
                            User::role($role)
                                ->get()
                                ->each(function (User $user) use ($permission) {
                                    $user->givePermissionTo($permission);
                                });
                        }
                    });
            });
    }
}
