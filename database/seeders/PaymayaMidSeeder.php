<?php

namespace Database\Seeders;

use App\Models\PaymayaMid;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class PaymayaMidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (App::isProduction()) {
            return;
        }

        if (
            ($public = config('services.paymaya.vault.public_key'))
            && ($secret = config('services.paymaya.vault.secret_key'))
        ) {
            PaymayaMid::make([
                'mid' => '123456789',
                'business_segment' => 'All / Others',
                'mdr' => 1,
                'mcc' => '1234',
                'is_vault' => true,
            ])->forceFill([
                'public_key' => $public,
                'secret_key' => $secret,
            ])->save();
        }

        if (
            ($public = config('services.paymaya.pwp.public_key'))
            && ($secret = config('services.paymaya.pwp.secret_key'))
        ) {
            PaymayaMid::make([
                'mid' => '987654321',
                'business_segment' => 'All / Others',
                'mdr' => 1,
                'mcc' => '9876',
                'is_pwp' => true,
            ])->forceFill([
                'public_key' => $public,
                'secret_key' => $secret,
            ])->save();
        }
    }
}
