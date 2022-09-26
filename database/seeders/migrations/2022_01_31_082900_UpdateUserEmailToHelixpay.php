<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateUserEmailToHelixpay_2022_01_31_082900 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::transaction(function () {
            User::query()
                ->cursor()
                ->tapEach(function (User $user)  {
                    $domain = Arr::last(explode('@', $user->email));
                    $user->update([
                        'email' => Str::replaceFirst($domain, 'helixpay.ph', $user->email)
                    ]);
                })
                ->all();
        });
    }
}
