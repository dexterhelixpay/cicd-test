<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevelopmentUserSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::truncate();
        $password = bcrypt('demo1234');
        $users = [
            [
                'name' => 'Jeff',
                'email' => 'jeff@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Issa',
                'email' => 'issa@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Danilo Perez Jr.',
                'email' => 'danilo@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Rachelle',
                'email' => 'chelle@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Ryan',
                'email' => 'jrc@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Jong',
                'email' => 'jong@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Kenneth',
                'email' => 'johnkenneth@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Eric',
                'email' => 'eric@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Amiel',
                'email' => 'amiel@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
            [
                'name' => 'Christine',
                'email' => 'jan@helixpay.ph',
                'password' => $password,
                'email_verified_at' => now(),
                'password_updated_at' => now()
            ],
        ];

        collect($users)
            ->each(function($user){
                User::create($user);
            });

    }
}
