<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (!app()->isProduction()) {
            User::withoutEvents(function () {
                collect([
                    'jeff' => 'Jefferson Asis',
                    'ryan' => 'John Ryan Camatog',
                    'jong' => 'Jonan Pineda',
                    'jayson' => 'Jayson Habitan',
                    'johnkenneth' => 'John Kenneth De Lara',
                    'john' => 'John Amiel Capistrano',
                    'jan' => 'Jan Christine Lee',
                    'issa' => 'Isabelle Miranda',
                    'danilo' => 'Danilo Perez Jr.',
                    'rachelle' => 'Rachelle Seguerra',
                    'bayanibrew' => 'Bayani Brew',
                    'bukidnoncoffee' => 'Bukidnon Coffee Roasters',
                ])->each(function ($name, $username) {
                    User::create([
                        'email' => "{$username}@goodwork.ph",
                        'password' => bcrypt('demo1234'),
                        'name' => $name,
                    ]);
                });
            });
        }
    }
}
