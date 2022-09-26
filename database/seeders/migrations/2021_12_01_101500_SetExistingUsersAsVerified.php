<?php

use App\Models\User;
use Illuminate\Database\Seeder;

class SetExistingUsersAsVerified_2021_12_01_101500 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::query()
            ->whereNull('email_verified_at')
            ->cursor()
            ->tapEach(fn(User $user)=>
                $user->update(['email_verified_at' => date('Y-m-d')])
            )
            ->all();

    }
}
