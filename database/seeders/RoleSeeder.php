<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        collect(['Owner', 'Admin'])
            ->each(function ($name) {
                Role::updateOrCreate(compact('name') + [
                    'guard_name' => 'merchant',
                ]);
            });

        collect(['Super Admin', 'Admin'])
            ->each(function ($name) {
                Role::updateOrCreate(compact('name') + [
                    'guard_name' => 'user',
                ]);
            });
    }
}
