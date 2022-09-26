<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetProviderForPersonalAccessClients_2021_12_16_171800 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('oauth_clients')
            ->where('personal_access_client', 1)
            ->get()
            ->each(function ($client) {
                if (!preg_match('/^([A-Za-z ]+?) Personal Access Client$/', $client->name, $matches)) {
                    return;
                }

                DB::table('oauth_clients')
                    ->where('id', $client->id)
                    ->update(['provider' => Str::snake($matches[1])]);
            });
    }
}
