<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
class UpdateAdminPermissions_2022_02_02_021200 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            Role::where('name','Admin')
                ->where('guard_name','user')
                ->first()
                ->syncPermissions([
                    "CP: Merchants - View",
                    "CP: Merchants - Edit",
                    "CP: Merchants - Log in to Store",
                    "CP: Merchants - Manage City Group",
                    "CP: Merchants - Manage Custom Fields",
                    "CP: Merchants - Manage Shopify Settings",
                    "CP: Merchants - Add",
                ]);
        });
    }
}
